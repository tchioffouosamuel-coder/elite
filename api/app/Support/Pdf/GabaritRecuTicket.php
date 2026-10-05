<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Models\Setting;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Mise en page commune des reçus (scolarité, transport, préinscription),
 * reprise du ticket papier en usage dans l'établissement : rouleau de 80 mm
 * composé à l'italienne (80 × 200 mm, le pilote de l'imprimante thermique
 * fait pivoter la page), en trois zones :
 *
 * - à gauche, imprimés de côté, les historiques (versements, accessoires) ;
 * - un trait épais ;
 * - à droite, l'en-tête (nom FR, sigle, nom EN, autorisation, adresse), puis
 *   les mentions de l'élève, les montants, et la zone du cachet.
 *
 * Les générateurs de reçus ne font que réunir les données (cf. `rendre()`).
 */
class GabaritRecuTicket
{
    use RenduDocument;

    private const FORMAT = [80, 200];

    /** Largeur du filigrane : tient dans les 80 mm de haut du ticket couché. */
    private const FILIGRANE_LARGEUR = 55;

    /** Abscisse du trait épais séparant les historiques du corps du reçu. */
    private const SEPARATEUR = 40;

    /** Lignes d'historique au-delà desquelles les plus anciennes sont résumées. */
    private const MAX_VERSEMENTS = 5;

    private const MAX_ACCESSOIRES = 4;

    /**
     * @param  array{
     *     titre: string,
     *     mentions: list<array{0: string, 1: string}>,
     *     montants: list<array{0: string, 1: string}>,
     *     versements: list<array{0: string, 1: int}>,
     *     accessoires?: list<array{0: string, 1: int}>|null,
     *     numero: string,
     *     encaisseur: ?string,
     *     qr?: ?string,
     *     annule?: bool,
     * }  $recu
     */
    public function rendre(School $school, array $recu): string
    {
        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'orientation' => 'L',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        MpdfFactory::appliquerFiligrane($mpdf, $school, self::FILIGRANE_LARGEUR);
        $mpdf->SetTitle('Reçu '.$recu['numero']);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$this->styles().'</style></head><body>'
            // Le trait et le corps sont posés en absolu ; seul le tableau des
            // historiques reste dans le flux : mPDF ne sait faire pivoter qu'un
            // tableau de premier niveau, qu'il place alors en haut à gauche.
            .'<div style="position:absolute;left:'.self::SEPARATEUR.'mm;top:4mm;height:72mm;width:1mm;border-left:0.9mm solid #000"></div>'
            .$this->enTete($school)
            .$this->bloc(43, 31, 27, $this->mentions($recu['mentions']))
            // Titre sur toute la largeur restante, montants dessous ; le
            // cachet apposé à la main déborde sur leur droite, comme sur le
            // ticket papier.
            .$this->bloc(75, 121, 26, '<div class="titre">'.$this->e($recu['titre']).'</div>')
            .$this->bloc(77, 100, 33, $this->corps($recu))
            .$this->bloc(176, 20, 51, $this->zoneCachet($recu))
            .$this->historiques($recu)
            .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function styles(): string
    {
        return 'body{font-family:dejavuserifcondensed,serif;font-size:3mm;color:#000;margin:0}'
            .'table{border-collapse:collapse}'
            .'.ecole{font-weight:bold;font-size:3mm;line-height:1.15;text-align:center}'
            .'.sigle{font-weight:bold;font-size:5mm;text-align:center}'
            .'.mention-ecole{font-size:2.3mm;font-style:italic;text-align:center;line-height:1.3}'
            .'.titre{font-weight:bold;font-style:italic;font-size:4.2mm;text-decoration:underline;text-align:center}'
            .'.cle{font-weight:bold;font-size:3mm;text-decoration:underline;text-align:center}'
            .'.valeur{font-size:2.8mm;text-align:center;padding-bottom:2.2mm}'
            .'.lib{font-weight:bold;font-size:3.2mm;text-decoration:underline;padding:0.9mm 2mm 0.9mm 0;vertical-align:top;white-space:nowrap}'
            .'.val{font-weight:bold;font-size:3.4mm;padding:0.9mm 0;vertical-align:top}'
            .'.hist td{font-size:2.7mm;padding:0.4mm 1mm;border-bottom:0.2mm solid #000}'
            .'.hist .t{font-size:2.9mm;border-bottom:none;padding-top:1.2mm}'
            .'.pied{font-size:2.4mm;text-align:center}'
            .'.annule{color:#ac3527;font-weight:bold;font-size:3.6mm;text-align:center;margin-top:1mm}';
    }

    /** Bloc positionné en absolu : mPDF ignore marges et centrage d'un bloc placé dans une cellule. */
    private function bloc(float $x, float $largeur, float $y, string $contenu): string
    {
        return '<div style="position:absolute;left:'.$x.'mm;top:'.$y.'mm;width:'.$largeur.'mm">'.$contenu.'</div>';
    }

    /**
     * Nom FR à gauche, sigle au centre, nom EN à droite, puis l'autorisation
     * d'ouverture et l'adresse sur toute la largeur. Le nom est la dernière
     * ligne de l'en-tête officiel (`header_fr`/`header_en`) : le cartouche
     * ministériel complet ne tiendrait pas sur 80 mm de haut.
     */
    private function enTete(School $school): string
    {
        $autorisation = Setting::get($school->id, 'numero_autorisation_ouverture');
        $contacts = array_filter([
            $school->address,
            $school->phone ? 'TEL : '.$school->phone : null,
            $school->email ? 'e-mail : '.$school->email : null,
        ]);

        $mentions = ($autorisation ? '<div>Autorisation d\'ouverture / Opening order N° : '.$this->e($autorisation).'</div>' : '')
            .($contacts !== [] ? '<div>'.$this->e(implode(', ', $contacts)).'</div>' : '');

        return $this->bloc(43, 52, 4, '<div class="ecole">'.$this->e($this->nomEtablissement($school->header_fr, $school->name)).'</div>')
            .$this->bloc(97, 42, 5, '<div class="sigle">'.$this->e(mb_strtoupper($school->code ?: $school->name)).'</div>')
            .$this->bloc(141, 55, 4, '<div class="ecole">'.$this->e($this->nomEtablissement($school->header_en, $school->name)).'</div>')
            .$this->bloc(43, 153, 15.5, '<div class="mention-ecole">'.$mentions.'</div>');
    }

    /** Dernière ligne non vide d'un en-tête riche — par convention, le nom de l'établissement. */
    private function nomEtablissement(?string $html, string $repli): string
    {
        $texte = strip_tags(preg_replace('#<br\s*/?>|</p>|</div>#i', "\n", (string) $html));
        $lignes = array_values(array_filter(array_map(
            fn ($l) => trim(html_entity_decode($l, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            explode("\n", $texte),
        ), fn ($l) => $l !== '' && ! preg_match('/^\*+$/', $l)));

        return mb_strtoupper($lignes === [] ? $repli : end($lignes));
    }

    /** @param  list<array{0: string, 1: string}>  $mentions */
    private function mentions(array $mentions): string
    {
        $html = '';
        foreach ($mentions as [$cle, $valeur]) {
            $html .= '<div class="cle">'.$this->e($cle).'</div><div class="valeur">'.$this->e($valeur).'</div>';
        }

        return $html;
    }

    /** @param  array<string, mixed>  $recu */
    private function corps(array $recu): string
    {
        $html = '<table>';
        foreach ($recu['montants'] as [$cle, $valeur]) {
            $html .= '<tr><td class="lib">'.$this->e($cle).'</td><td class="val">'.$this->e($valeur).'</td></tr>';
        }
        $html .= '</table>';

        if ($recu['annule'] ?? false) {
            $html .= '<div class="annule">*** REÇU ANNULÉ / RECEIPT CANCELLED ***</div>';
        }

        return $html;
    }

    /**
     * Coin inférieur droit : code QR de vérification d'authenticité, numéro
     * du reçu et caissier. Le reste de la droite du ticket est laissé libre
     * pour le cachet et la signature, apposés à la main au comptoir.
     *
     * @param  array<string, mixed>  $recu
     */
    private function zoneCachet(array $recu): string
    {
        $qr = ! empty($recu['qr'])
            ? '<img src="'.$recu['qr'].'" style="width:13mm;height:13mm">'
            : '';

        return '<div class="pied">'.$qr.'<br>'
            .'N° '.$this->e($recu['numero']).'<br>'
            .'<b>'.$this->e(mb_strtoupper((string) ($recu['encaisseur'] ?? ''))).'</b>'
            .'</div>';
    }

    /**
     * Historiques, imprimés de côté le long du bord gauche du ticket : un
     * tableau pivoté d'un quart de tour, dont la largeur devient la hauteur
     * du ticket.
     *
     * @param  array<string, mixed>  $recu
     */
    private function historiques(array $recu): string
    {
        $lignes = $this->section('Historique des versements', ['Date de paiement', 'Montant'], $this->resumer($recu['versements'], self::MAX_VERSEMENTS, 'versement'));

        if (($recu['accessoires'] ?? null) !== null) {
            $lignes .= $this->section('Historique des accessoires', ['Accessoires', 'Montant'], $this->resumer($recu['accessoires'], self::MAX_ACCESSOIRES, 'accessoire'));
        }

        return '<table class="hist" rotate="90" style="width:72mm">'.$lignes.'</table>';
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array{0: string, 1: string}>  $lignes
     */
    private function section(string $titre, array $colonnes, array $lignes): string
    {
        $html = '<tr><td class="t" colspan="2" style="text-align:center"><b>'.$this->e($titre).'</b></td></tr>'
            .'<tr><td style="width:60%">'.$this->e($colonnes[0]).'</td><td style="text-align:right">'.$this->e($colonnes[1]).'</td></tr>';

        foreach ($lignes as [$libelle, $montant]) {
            $html .= '<tr><td>'.$this->e($libelle).'</td><td style="text-align:right">'.$this->e($montant).'</td></tr>';
        }

        return $html;
    }

    /**
     * Les plus récentes d'abord ; au-delà du maximum, les plus anciennes
     * sont regroupées sur une ligne pour que le ticket garde sa taille.
     *
     * @param  list<array{0: string, 1: int}>  $lignes
     * @return list<array{0: string, 1: string}>
     */
    private function resumer(array $lignes, int $max, string $nom): array
    {
        $affichees = array_slice($lignes, 0, $max - 1);
        $reste = array_slice($lignes, $max - 1);

        if (count($reste) === 1) {
            $affichees[] = $reste[0];
        } elseif ($reste !== []) {
            $affichees[] = ['+ '.count($reste).' '.$nom.'s antérieurs', array_sum(array_column($reste, 1))];
        }

        return array_map(fn ($l) => [$l[0], self::montant($l[1])], $affichees);
    }

    public static function montant(int $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    public static function francs(int $valeur): string
    {
        return self::montant($valeur).' cfa';
    }

    public static function libelleMode(string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'MOBILE MONEY',
            'virement' => 'BANK TRANSFER',
            'cheque' => 'CHEQUE',
            'depot_bancaire' => 'BANK DEPOSIT',
            default => 'CASH',
        };
    }
}
