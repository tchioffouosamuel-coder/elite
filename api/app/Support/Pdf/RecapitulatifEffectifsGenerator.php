<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Récapitulatif d'effectifs façon rentrée scolaire : une grille Garçons /
 * Filles / Total en lignes, Nouveaux / Redoublants / Camerounais / Réfugiés
 * en colonnes, répétée une fois par table demandée — une par école, ou une
 * par (école, sous-système) quand l'appelant a éclaté le détail.
 */
class RecapitulatifEffectifsGenerator
{
    use RenduDocument;

    /**
     * @param  list<array{titre: string, garcons: array, filles: array, total: array}>  $tables
     */
    public function build(?School $school, string $titreDocument, array $tables): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle($titreDocument);

        $corps = '';
        foreach ($tables as $table) {
            $corps .= $this->tableau($table['titre'], $table['garcons'], $table['filles'], $table['total']);
        }

        if ($corps === '') {
            $corps = '<p style="text-align:center;padding:8mm;">Aucune donnée sur ce périmètre.</p>';
        }

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .($school ? $this->enTeteEcole($school).'<hr>' : '')
                .'<div style="text-align:center;line-height:1.4;margin-bottom:2mm;">'
                .'<span class="titre">'.$this->e($titreDocument).'</span>'
                .'</div>'
                .$corps
                .($school ? $this->signatureChef($school) : '')
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.recap{page-break-inside:avoid;margin-bottom:8mm}'
            .'.recap-titre{background:'.self::ARDOISE.';color:#fff;padding:1.8mm;text-align:center;'
            .'font-size:2.9mm;font-weight:bold;margin-bottom:1mm}'
            .'.recap td,.recap th{font-size:2.8mm}'
            .'.recap .libelle{text-align:left;font-weight:bold;background:#f7f7f5}'
            .'.recap tfoot td{font-weight:bold;background:#f0efe9}';
    }

    private function tableau(string $titre, array $garcons, array $filles, array $total): string
    {
        $ligne = fn (string $libelle, array $v, bool $gras = false) => '<tr'.($gras ? ' style="font-weight:bold;background:#f0efe9;"' : '').'>'
            .'<td class="libelle">'.$this->e($libelle).'</td>'
            .'<td>'.$v['nouveaux'].'</td>'
            .'<td>'.$v['redoublants'].'</td>'
            .'<td>'.$v['camerounais'].'</td>'
            .'<td>'.$v['refugies'].'</td>'
            .'<td>'.$v['effectif'].'</td>'
            .'</tr>';

        return '<div class="recap">'
            .'<div class="recap-titre">'.$this->e($titre).'</div>'
            .'<table class="recap"><thead><tr>'
            .'<th></th>'
            .'<th>Nouveaux<br><i>New</i></th>'
            .'<th>Redoublants<br><i>Repeaters</i></th>'
            .'<th>Camerounais<br><i>Cameroonian</i></th>'
            .'<th>Réfugiés<br><i>Refugees</i></th>'
            .'<th>Effectif<br><i>Total</i></th>'
            .'</tr></thead><tbody>'
            .$ligne('Garçons', $garcons)
            .$ligne('Filles', $filles)
            .$ligne('Total', $total, true)
            .'</tbody></table>'
            .'</div>';
    }
}
