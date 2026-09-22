<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/** PDF générique des listes personnalisées hors classe. */
class ListePersonnaliseeGenerator
{
    use RenduDocument;

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, array{fr: string, en: string}>  $definitions
     * @param  array<string, string>  $meta
     */
    public function build(School $school, string $titreFr, string $titreEn, array $colonnes, array $lignes, array $definitions, array $meta = []): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => count($colonnes) > 6 ? 'L' : 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle($titreFr);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<hr>'
                .$this->titre($titreFr, $titreEn, count($lignes), $meta)
                .$this->tableau($colonnes, $lignes, $definitions)
                .$this->signatureChef($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0}'
            .'.meta{text-align:center;font-size:2.6mm;color:#555;margin-bottom:2mm}'
            .'.liste th{font-size:2.5mm}'
            .'.liste td{font-size:2.65mm;padding:1.15mm 1mm}'
            .'.liste tbody tr:nth-child(even) td{background:#f7f7f5}';
    }

    /** @param array<string, string> $meta */
    private function titre(string $titreFr, string $titreEn, int $effectif, array $meta): string
    {
        $details = implode(' &nbsp;|&nbsp; ', array_map(
            fn ($cle, $valeur) => $this->e($cle).' : '.$this->e($valeur),
            array_keys($meta),
            $meta,
        ));

        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">'.$this->e($titreFr).'</span><br>'
            .'<span class="titre-en">'.$this->e($titreEn).'</span>'
            .'</div>'
            .($details !== '' ? '<div class="meta">'.$details.'</div>' : '')
            .'<div class="bandeau">Effectif <i>/ Headcount</i> : '.$effectif.'</div>';
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, array{fr: string, en: string}>  $definitions
     */
    private function tableau(array $colonnes, array $lignes, array $definitions): string
    {
        $entetes = '';
        foreach ($colonnes as $colonne) {
            $definition = $definitions[$colonne] ?? ['fr' => $colonne, 'en' => $colonne];
            $entetes .= '<th>'.$this->e($definition['fr']).'<br><i>'.$this->e($definition['en']).'</i></th>';
        }

        $corps = '';
        foreach ($lignes as $donnee) {
            $corps .= '<tr>';
            foreach ($colonnes as $colonne) {
                $corps .= '<td>'.$this->e((string) ($donnee[$colonne] ?? '—')).'</td>';
            }
            $corps .= '</tr>';
        }

        if ($corps === '') {
            $corps = '<tr><td colspan="'.count($colonnes).'" style="padding:6mm;">Aucune donnée.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'.$entetes.'</tr></thead><tbody>'.$corps.'</tbody></table>';
    }
}
