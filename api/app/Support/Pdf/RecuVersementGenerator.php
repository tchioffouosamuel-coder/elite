<?php

namespace App\Support\Pdf;

use App\Models\DossierScolarite;
use App\Models\School;
use App\Models\Versement;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Reçu d'encaissement, au format du ticket imprimé par l'établissement :
 * rouleau de 80 mm, deux colonnes de mentions, puis l'historique des
 * versements et celui des accessoires.
 *
 * Le ticket est repris tel quel parce que c'est lui que la famille conserve et
 * présente au comptoir : en changer la forme obligerait le caissier à
 * réapprendre à lire ce qu'il remet, et rendrait incomparables les reçus émis
 * avant et après la reprise.
 */
class RecuVersementGenerator
{
    use RenduDocument;

    /** Largeur du rouleau, hauteur généreuse : mPDF coupe au contenu à l'impression. */
    private const FORMAT = [80, 200];

    public function build(Versement $versement): string
    {
        $versement->loadMissing(['lignes', 'encaisseur', 'dossier.eleve.classe', 'dossier.anneeScolaire', 'dossier.fraisAnnexes', 'dossier.versements']);

        $dossier = $versement->dossier;
        $school = $dossier->school;

        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        $mpdf->SetTitle('Reçu '.$versement->numero_recu);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->styles()
                .'</style></head><body>'
                .$this->enTete($school)
                .$this->titre()
                .$this->mentions($versement, $dossier)
                .$this->repartitionVersement($versement)
                .$this->historiqueVersements($dossier, $versement)
                .$this->historiqueAccessoires($dossier)
                .$this->pied($versement)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function styles(): string
    {
        return 'body{font-family:montserrat,sans-serif;font-size:2.4mm;color:#000;margin:0}'
            .'.centre{text-align:center}'
            .'.ecole{font-weight:bold;font-size:2.7mm;line-height:1.2}'
            .'.mentions{font-size:2mm;line-height:1.25}'
            .'.titre{font-weight:bold;font-size:3mm;text-align:center;text-decoration:underline;margin:2mm 0}'
            .'table{width:100%;border-collapse:collapse}'
            .'td{padding:0.4mm 0;vertical-align:top;font-size:2.4mm}'
            .'.cle{font-weight:bold;width:42%}'
            .'.sep{border-top:0.4mm dashed #000;margin:2mm 0}'
            .'.section{font-weight:bold;font-size:2.5mm;margin:1.5mm 0 0.5mm}'
            .'.hist td{border-bottom:0.2mm dotted #999;font-size:2.3mm;padding:0.6mm 0}'
            .'.montant{text-align:right;font-weight:bold}'
            .'.total{font-weight:bold;font-size:2.7mm}'
            .'.pied{font-size:2.1mm;margin-top:2.5mm}'
            .'.annule{color:#ac3527;font-weight:bold;text-align:center;font-size:3mm;margin:1.5mm 0}';
    }

    private function enTete(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);

        return '<div class="centre">'
            .($logo ? '<img src="'.$this->e($logo).'" style="width:16mm"><br>' : '')
            .'<span class="ecole">'.$this->e(mb_strtoupper($school->name)).'</span>'
            .'</div>'
            .'<div class="centre mentions">'
            .EnTeteHtml::render($school->header_fr)
            .($school->address ? $this->e($school->address).'<br>' : '')
            .($school->phone ? 'Tél : '.$this->e($school->phone) : '')
            .($school->email ? ' · '.$this->e($school->email) : '')
            .'</div>';
    }

    private function titre(): string
    {
        return '<div class="titre">REÇU DE PAIEMENT<br>DES FRAIS DE SCOLARITÉ</div>';
    }

    private function mentions(Versement $versement, DossierScolarite $dossier): string
    {
        $eleve = $dossier->eleve;

        $lignes = [
            ['Matricule', $eleve->matricule ?: '—'],
            ['Nom / Name', mb_strtoupper($eleve->nom_complet)],
            ['Classe / Class', $eleve->classe?->nom ?? '—'],
            ['Année scolaire', $dossier->anneeScolaire?->libelle ?? '—'],
            ['Date', $versement->date_versement->format('d/m/Y')],
            ['Mode / Method', $this->libelleMode($versement->mode)],
        ];

        $html = '<table>';
        foreach ($lignes as [$cle, $valeur]) {
            $html .= '<tr><td class="cle">'.$this->e($cle).'</td><td>'.$this->e((string) $valeur).'</td></tr>';
        }

        $html .= '</table><div class="sep"></div><table>'
            .$this->ligneMontant('Frais de scolarité', $dossier->total_du)
            .$this->ligneMontant('Montant perçu / Paid', $versement->montant, true)
            .$this->ligneMontant('Reste à payer / Left', $dossier->reste_a_payer, true)
            .'</table>';

        if ($versement->estAnnule()) {
            $html .= '<div class="annule">*** REÇU ANNULÉ ***</div>';
        }

        return $html;
    }

    private function ligneMontant(string $libelle, int $montant, bool $fort = false): string
    {
        $classe = $fort ? ' total' : '';

        return '<tr><td class="cle'.$classe.'">'.$this->e($libelle).'</td>'
            .'<td class="montant'.$classe.'">'.$this->francs($montant).'</td></tr>';
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

        $html = '<div class="sep"></div><div class="section">Répartition du versement</div><table class="hist">';

        foreach ($lignes as $ligne) {
            $html .= '<tr><td>'.$this->e($ligne->libelle).'</td>'
                .'<td class="montant">'.$this->francs($ligne->montant).'</td></tr>';
        }

        return $html.'</table>';
    }

    /**
     * Historique complet des versements du dossier : la famille voit ce qu'elle
     * a déjà réglé, pas seulement le paiement du jour. Le versement en cours
     * est signalé pour qu'on retrouve la ligne correspondant au ticket en main.
     */
    private function historiqueVersements(DossierScolarite $dossier, Versement $courant): string
    {
        $versements = $dossier->versements->whereNull('annule_le')->sortBy('date_versement');

        $html = '<div class="sep"></div><div class="section">Historique des versements</div>'
            .'<table class="hist"><tr><td><b>Date</b></td><td class="montant"><b>Montant</b></td></tr>';

        foreach ($versements as $v) {
            $marque = $v->id === $courant->id ? ' ◄' : '';
            $html .= '<tr><td>'.$v->date_versement->format('d/m/Y').$marque.'</td>'
                .'<td class="montant">'.$this->francs($v->montant).'</td></tr>';
        }

        return $html.'<tr><td class="total">Total</td>'
            .'<td class="montant total">'.$this->francs((int) $versements->sum('montant')).'</td></tr></table>';
    }

    private function historiqueAccessoires(DossierScolarite $dossier): string
    {
        $html = '<div class="section">Historique des accessoires</div><table class="hist">';

        if ($dossier->fraisAnnexes->isEmpty()) {
            return $html.'<tr><td colspan="2">Aucun accessoire.</td></tr></table>';
        }

        $html .= '<tr><td><b>Accessoire</b></td><td class="montant"><b>Montant</b></td></tr>';
        foreach ($dossier->fraisAnnexes as $frais) {
            $html .= '<tr><td>'.$this->e($frais->libelle).'</td>'
                .'<td class="montant">'.$this->francs($frais->montant).'</td></tr>';
        }

        return $html.'<tr><td class="total">Total</td>'
            .'<td class="montant total">'.$this->francs($dossier->total_frais_annexes).'</td></tr></table>';
    }

    private function pied(Versement $versement): string
    {
        return '<div class="sep"></div><div class="pied">'
            .'Reçu N° <b>'.$this->e($versement->numero_recu).'</b><br>'
            .'Encaissé par : '.$this->e($versement->encaisseur?->name ?? '—').'<br>'
            .'<i>Conservez ce reçu, il vous sera réclamé.</i>'
            .'</div>';
    }

    private function libelleMode(string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile Money',
            'virement' => 'Virement',
            'cheque' => 'Chèque',
            'depot_bancaire' => 'Dépôt bancaire',
            default => 'Espèces / Cash',
        };
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }
}
