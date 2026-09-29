<?php

namespace App\Support\Pdf;

use App\Models\DossierScolarite;
use App\Models\School;
use App\Models\Versement;
use App\Support\Pdf\Concerns\RenduDocument;
use App\Support\SignatureVersement;
use Endroid\QrCode\Builder\Builder;
use Mpdf\Output\Destination;

/**
 * Reçu d'encaissement, au format du ticket imprimé par l'établissement :
 * rouleau de 80 mm, deux colonnes de mentions, puis l'historique des
 * versements et un code QR pour traçabilité.
 *
 * Le ticket est composé à l'italienne : le texte court le long du rouleau
 * (80 mm de haut, lu en tenant le reçu dans sa largeur), en quatre colonnes
 * — en-tête, mentions et montants, répartition et historique, QR et pied.
 * En portrait, les libellés bilingues n'avaient que 72 mm de large et se
 * repliaient sur deux lignes, allongeant le ticket sans rien y ajouter.
 * Le pilote de l'imprimante thermique fait pivoter la page pour la poser
 * sur le rouleau.
 */
class RecuVersementGenerator
{
    use RenduDocument;

    /** Largeur du rouleau × longueur du ticket, composé à l'italienne (cf. `orientation`). */
    private const FORMAT = [80, 200];

    /** Largeur du filigrane : tient dans les 80 mm de haut du ticket couché. */
    private const FILIGRANE_LARGEUR = 55;

    /**
     * Colonnes du ticket, [abscisse, largeur] en mm. Positionnées en absolu :
     * mPDF ignore l'essentiel de la mise en forme des blocs (centrage, marges)
     * placés dans une cellule de tableau.
     */
    private const COLONNES = [[4, 36], [44, 66], [114, 50], [168, 28]];

    public function build(Versement $versement): string
    {
        $versement->loadMissing(['lignes', 'encaisseur', 'dossier.eleve.classe', 'dossier.anneeScolaire', 'dossier.fraisAnnexes', 'dossier.versements', 'dossier.busAffectations.trajet']);

        $dossier = $versement->dossier;
        $school = $dossier->school;

        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'orientation' => 'L',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        // Le filigrane par défaut est calé sur une A4 : sur 80 mm de haut, on
        // lui impose une taille explicite pour qu'il reste entier et centré.
        MpdfFactory::appliquerFiligrane($mpdf, $school, self::FILIGRANE_LARGEUR);
        $mpdf->SetTitle('Reçu ' . $versement->numero_recu);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                . $this->styles()
                . '</style></head><body>'
                . $this->colonne(self::COLONNES[0], $this->enTete($school) . $this->titre($versement))
                . $this->colonne(self::COLONNES[1], $this->mentions($versement, $dossier))
                . $this->colonne(self::COLONNES[2], $this->repartitionVersement($versement) . $this->historiqueVersements($dossier, $versement))
                . $this->colonne(self::COLONNES[3], $this->qrCode($versement) . $this->pied($versement))
                . '</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function styles(): string
    {
        return 'body{font-family:montserrat,sans-serif;font-size:3mm;color:#000;margin:0}'
            . '.centre{text-align:center}'
            . '.ecole{font-weight:bold;font-size:3.3mm;line-height:1.2}'
            . '.titre{font-weight:bold;font-size:3.5mm;text-align:center;text-decoration:underline;margin:2mm 0 0}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'td{padding:0.5mm 0;vertical-align:top;font-size:3mm}'
            . '.cle{font-weight:bold;width:45%}'
            . '.sep{border-top:0.4mm dashed #000;margin:1.5mm 0}'
            . '.section{font-weight:bold;font-size:3mm;margin:0 0 0.5mm}'
            . '.section-suite{margin-top:2mm}'
            . '.hist td{border-bottom:0.2mm dotted #999;font-size:2.8mm;padding:0.6mm 0}'
            . '.montant{text-align:right;font-weight:bold}'
            . '.total{font-weight:bold;font-size:3.3mm}'
            . '.pied{font-size:2.6mm;margin-top:2mm;text-align:left}'
            . '.annule{color:#ac3527;font-weight:bold;text-align:center;font-size:3.5mm;margin:1.5mm 0}';
    }

    /** Une colonne du ticket, séparée de la précédente par un trait tireté. */
    private function colonne(array $colonne, string $contenu): string
    {
        [$x, $largeur] = $colonne;
        $trait = $x > 4
            ? '<div style="position:absolute;left:' . ($x - 2) . 'mm;top:4mm;height:72mm;width:1mm;border-left:0.3mm dashed #000"></div>'
            : '';

        return $trait . '<div style="position:absolute;left:' . $x . 'mm;top:4mm;width:' . $largeur . 'mm">' . $contenu . '</div>';
    }

    /**
     * Volontairement dépouillé du cartouche administratif (`header_fr`,
     * adresse, contacts) que portent les autres documents officiels
     * (bulletins, PV…) : le reçu est remis au comptoir dans l'instant, pas
     * archivé comme pièce officielle — seuls le nom et le logo de
     * l'établissement suffisent à l'identifier.
     */
    private function enTete(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);

        return '<div class="centre">'
            . ($logo ? '<img src="' . $this->e($logo) . '" style="width:16mm"><br>' : '')
            . '<span class="ecole">' . $this->e(mb_strtoupper($school->name)) . '</span>'
            . '</div>';
    }

    private function titre(Versement $versement): string
    {
        $titre = $this->estBusSeul($versement) ? 'DES FRAIS DE BUS / SCHOOL TRANSPORT FEES' : 'DES FRAIS DE SCOLARITÉ / SCHOOL FEES';

        return '<div class="titre">REÇU DE PAIEMENT / PAYMENT RECEIPT<br>' . $titre . '</div>';
    }

    private function mentions(Versement $versement, DossierScolarite $dossier): string
    {
        $eleve = $dossier->eleve;

        $lignes = [
            ['Matricule / Student ID', $eleve->matricule ?: '—'],
            ['Nom / Name', mb_strtoupper($eleve->nom_complet)],
            ['Classe / Class', $eleve->classe?->nom ?? '—'],
            ['Année scolaire / School year', $dossier->anneeScolaire?->libelle ?? '—'],
            ['Date / Date', $versement->date_versement->format('d/m/Y')],
            ['Mode / Payment method', $this->libelleMode($versement->mode)],
        ];

        $html = '<table>';
        foreach ($lignes as [$cle, $valeur]) {
            $html .= '<tr><td class="cle">' . $this->e($cle) . '</td><td>' . $this->e((string) $valeur) . '</td></tr>';
        }

        $lignes = $versement->lignes->where('montant', '>', 0);
        $busSeul = $this->estBusSeul($versement);
        $rubriques = $dossier->rubriques;
        $rubriqueBus = collect($rubriques)->firstWhere('cle', 'bus');
        $montantDu = $busSeul ? (int) ($rubriqueBus['montant_du'] ?? 0) : $dossier->total_du;
        $montantPaye = $busSeul ? (int) $lignes->sum('montant') : $versement->montant;
        $libelleDu = $busSeul ? 'Frais de bus / School transport fees' : 'Frais de scolarité / School fees';

        $html .= '</table><div class="sep"></div><table>'
            . $this->ligneMontant($libelleDu, $montantDu)
            . ($dossier->remise > 0 ? $this->ligneMontant('Remise accordée / Discount', (int) $dossier->remise) : '')
            . $this->ligneMontant('Montant perçu / Amount paid', $montantPaye, true)
            . $this->ligneMontant('Reste à payer / Balance due', max(0, $montantDu - $montantPaye), true)
            . '</table>';

        if ($versement->estAnnule()) {
            $html .= '<div class="annule">*** REÇU ANNULÉ / RECEIPT CANCELLED ***</div>';
        }

        return $html;
    }

    private function estBusSeul(Versement $versement): bool
    {
        $lignes = $versement->lignes->where('montant', '>', 0);

        return $lignes->isNotEmpty() && $lignes->every(fn($ligne) => $ligne->affectation === 'bus');
    }

    private function ligneMontant(string $libelle, int $montant, bool $fort = false): string
    {
        $classe = $fort ? ' total' : '';

        return '<tr><td class="cle' . $classe . '">' . $this->e($libelle) . '</td>'
            . '<td class="montant' . $classe . '">' . $this->francs($montant) . '</td></tr>';
    }

    /**
     * Ce que couvre ce versement précisément — scolarité, tel frais annexe,
     * transport — pas seulement son total. Une famille qui verse un montant
     * partiel doit savoir sur quoi il a été imputé, sans avoir à recalculer
     * la ventilation automatique elle-même.
     */
    private function repartitionVersement(Versement $versement): string
    {
        $lignes = $versement->lignes->where('montant', '>', 0);

        if ($lignes->isEmpty()) {
            return '';
        }

        $html = '<div class="section">Répartition du versement / Payment allocation</div><table class="hist">';

        foreach ($lignes as $ligne) {
            $html .= '<tr><td>' . $this->e($this->libelleLigne($ligne->affectation, $ligne->libelle)) . '</td>'
                . '<td class="montant">' . $this->francs($ligne->montant) . '</td></tr>';
        }

        return $html . '</table>';
    }

    /**
     * Historique complet des versements du dossier : la famille voit ce qu'elle
     * a déjà réglé, pas seulement le paiement du jour. Le versement en cours
     * est signalé pour qu'on retrouve la ligne correspondant au ticket en main.
     */
    private function historiqueVersements(DossierScolarite $dossier, Versement $courant): string
    {
        $versements = $dossier->versements->whereNull('annule_le')->sortBy('date_versement');

        $html = '<div class="section section-suite">Historique des versements / Payment history</div>'
            . '<table class="hist"><tr><td><b>Date / Date</b></td><td class="montant"><b>Montant / Amount</b></td></tr>';

        foreach ($versements as $v) {
            // Montserrat n'a pas le glyphe ◄ : Symbola le fournit.
            $marque = $v->id === $courant->id ? ' <span style="font-family:symbola">◄</span>' : '';
            $html .= '<tr><td>' . $v->date_versement->format('d/m/Y') . $marque . '</td>'
                . '<td class="montant">' . $this->francs($v->montant) . '</td></tr>';
        }

        return $html . '<tr><td class="total">Total / Total</td>'
            . '<td class="montant total">' . $this->francs((int) $versements->sum('montant')) . '</td></tr></table>';
    }

    private function historiqueAccessoires(DossierScolarite $dossier): string
    {
        // Section supprimée : les accessoires ne sont pas pertinents sur le reçu
        return '';
    }

    /**
     * Code QR pointant vers la page publique de vérification d'authenticité
     * du reçu (cf. `SignatureVersement`) : dissuade la présentation d'un reçu
     * falsifié, sans rien stocker en base pour chaque versement encaissé.
     * En tête de la dernière colonne, au-dessus du pied — à scanner une
     * fois le reçu en main.
     */
    private function qrCode(Versement $versement): string
    {
        try {
            $qr = (new Builder)->build(
                data: SignatureVersement::lienVerification($versement->id),
                size: 200,
                margin: 4,
            );

            return '<div style="text-align:center">'
                . '<img src="' . $qr->getDataUri() . '" style="width:22mm;height:22mm">'
                . '<div class="pied">Authenticité / Authenticity : scannez / scan to verify this receipt.</div>'
                . '</div>';
        } catch (\Throwable) {
            return '';
        }
    }

    private function pied(Versement $versement): string
    {
        return '<div class="sep"></div><div class="pied">'
            . 'Reçu N° / Receipt No. <b>' . $this->e($versement->numero_recu) . '</b><br>'
            . 'Encaissé par / Collected by : ' . $this->e($versement->encaisseur?->name ?? '—') . '<br>'
            . '<i>Conservez ce reçu / Keep this receipt.</i>'
            . '</div>';
    }

    private function libelleMode(string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile Money / Mobile Money',
            'virement' => 'Virement / Bank transfer',
            'cheque' => 'Chèque / Cheque',
            'depot_bancaire' => 'Dépôt bancaire / Bank deposit',
            default => 'Espèces / Cash',
        };
    }

    private function libelleLigne(string $affectation, string $libelle): string
    {
        return match ($affectation) {
            'bus' => 'Transport scolaire / School transport',
            'scolarite' => 'Frais de scolarité / School fees',
            'report_dette' => 'Reliquat année précédente / Previous year balance',
            default => $libelle . ' / Additional fee',
        };
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' F';
    }
}
