<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Les exercices d'un établissement sur une seule page.
 *
 * Le classeur ne donne cette série qu'en feuilletant onze onglets, et c'est
 * précisément en la mettant à plat qu'on voit ce qu'un exercice isolé cache :
 * une balance qui plonge quand la construction s'emballe, un résultat
 * d'exploitation qui reste positif dessous.
 *
 * D'où les deux colonnes de solde côte à côte. Celle du document d'abord —
 * c'est celle que le comptable reconnaît — puis celle de l'exploitation, avec
 * l'investissement de l'année qui explique l'écart entre les deux.
 */
class SerieExercicesGenerator
{
    use RenduDocument;

    /** @param list<array<string, mixed>> $serie */
    public function build(School $school, array $serie): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);

        $mpdf->SetTitle('Série des exercices');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$this->enTeteEcole($school)
                .$this->titre($serie)
                .$this->tableau($serie)
                .$this->lecture($serie)
                .$this->signatureChef($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.se td{font-size:2.6mm;padding:1.3mm 1.5mm}'
            .'.se th{font-size:2.4mm}'
            .'.se .lib{text-align:left;font-weight:bold}'
            .'.se .num{text-align:right}'
            .'.se tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold}'
            .'.deficit{color:#ac3527;font-weight:bold}'
            .'.excedent{color:#1d7a35;font-weight:bold}'
            .'.section{font-weight:bold;font-size:3mm;color:'.self::ARDOISE.';margin:3mm 0 1mm;text-transform:uppercase}'
            .'.note{font-size:2.4mm;color:#555;text-align:left;line-height:1.5;margin-top:2mm}';
    }

    /** @param list<array<string, mixed>> $serie */
    private function titre(array $serie): string
    {
        $bornes = $serie === []
            ? ''
            : ' — de '.$this->e((string) $serie[0]['libelle']).' à '.$this->e((string) end($serie)['libelle']);

        return '<div style="text-align:center;margin-bottom:2mm">'
            .'<span class="titre">Série des exercices'.$bornes.'</span><br>'
            .'<span class="titre-en">Financial years at a glance</span>'
            .'</div>';
    }

    /** @param list<array<string, mixed>> $serie */
    private function tableau(array $serie): string
    {
        if ($serie === []) {
            return '<p class="note">Aucun exercice enregistré pour cet établissement.</p>';
        }

        $corps = '';
        $cumulBalance = 0;
        $cumulExploitation = 0;
        $cumulInvestissement = 0;

        foreach ($serie as $exercice) {
            $balance = (int) $exercice['balance'];
            $exploitation = (int) $exercice['resultat_exploitation'];

            $cumulBalance += $balance;
            $cumulExploitation += $exploitation;
            $cumulInvestissement += (int) $exercice['investissement'];

            $corps .= '<tr>'
                .'<td class="lib">'.$this->e((string) $exercice['libelle']).'</td>'
                .'<td class="num">'.(int) $exercice['effectif'].'</td>'
                .'<td class="num">'.$this->francs((int) $exercice['total_recettes']).'</td>'
                .'<td class="num">'.$this->francs((int) $exercice['total_depenses']).'</td>'
                .'<td class="num '.$this->ton($balance).'">'.$this->francs($balance).'</td>'
                .'<td class="num">'.$this->francs((int) $exercice['investissement']).'</td>'
                .'<td class="num '.$this->ton($exploitation).'">'.$this->francs($exploitation).'</td>'
                .'<td class="num">'.$this->francs((int) $exercice['apport_fondateur']).'</td>'
                .'</tr>';
        }

        return '<table class="se"><thead><tr>'
            .'<th class="lib">Exercice</th>'
            .'<th>Effectif</th>'
            .'<th>Recettes</th>'
            .'<th>Dépenses</th>'
            .'<th>Balance<br><i>du document</i></th>'
            .'<th>dont investissement</th>'
            .'<th>Résultat<br><i>d\'exploitation</i></th>'
            .'<th>Apport du fondateur</th>'
            .'</tr></thead><tbody>'.$corps.'</tbody>'
            .'<tfoot><tr class="total">'
            .'<td class="lib">Cumul</td><td></td><td></td><td></td>'
            .'<td class="num">'.$this->francs($cumulBalance).'</td>'
            .'<td class="num">'.$this->francs($cumulInvestissement).'</td>'
            .'<td class="num">'.$this->francs($cumulExploitation).'</td>'
            .'<td></td></tr></tfoot></table>';
    }

    /** @param list<array<string, mixed>> $serie */
    private function lecture(array $serie): string
    {
        if ($serie === []) {
            return '';
        }

        $cumulBalance = array_sum(array_column($serie, 'balance'));
        $cumulExploitation = array_sum(array_column($serie, 'resultat_exploitation'));
        $cumulInvestissement = array_sum(array_column($serie, 'investissement'));

        return '<div class="section">Ce que la série montre</div>'
            .'<div class="note">'
            .'Sur '.count($serie).' exercice(s), la balance du document cumule <b>'.$this->francs((int) $cumulBalance)
            .' F</b>, quand l\'exploitation cumule <b>'.$this->francs((int) $cumulExploitation).' F</b>. '
            .'L\'écart tient à <b>'.$this->francs((int) $cumulInvestissement).' F</b> de construction, passés en '
            .'charge l\'année du chantier au lieu d\'être étalés sur la durée des bâtiments, et aux apports de '
            .'l\'exploitant portés en colonne de dépenses. Un exercice déficitaire au document peut donc être '
            .'excédentaire en exploitation : c\'est l\'investissement qui creuse le solde, pas le fonctionnement.'
            .'</div>';
    }

    private function ton(int $montant): string
    {
        return $montant < 0 ? 'deficit' : 'excedent';
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
