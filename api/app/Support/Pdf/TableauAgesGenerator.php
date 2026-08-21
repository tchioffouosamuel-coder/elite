<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/** Pyramide des âges : effectif garçons/filles par âge révolu, sur le périmètre demandé (école, sous-système ou classe). */
class TableauAgesGenerator
{
    use RenduDocument;

    /**
     * @param  list<array{age: string, annees: int, mois: int, garcons: int, filles: int, total: int}>  $lignes
     */
    public function build(?School $school, string $perimetre, array $lignes): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle('Pyramide des âges');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .($school ? $this->enTeteEcole($school).'<hr>' : '')
                .$this->titre($perimetre)
                .$this->graphique($lignes)
                .$this->tableau($lignes)
                .($school ? $this->signatureChef($school) : '')
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0}'
            .'.ages th{font-size:2.8mm}'
            .'.ages td{font-size:2.8mm;padding:1.4mm 1mm}'
            .'.ages tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.ages tfoot td{font-weight:bold;background:#f0efe9}'
            .'.graphique{table-layout:fixed;margin:2mm 0}'
            .'.graphique td{vertical-align:bottom;text-align:center;padding:0 0.3mm;width:'.self::GRAPHIQUE_COLONNE_PC.'%}'
            .'.graphique .valeur{font-size:1.8mm;margin-bottom:0.5mm;color:'.self::ARDOISE.'}'
            .'.graphique .barre{background:'.self::ACCENT.'}'
            .'.graphique .repere{font-size:1.7mm;margin-top:0.8mm;white-space:nowrap;color:#666}';
    }

    /** Colonnes par rangée du graphique : détermine leur largeur — assez large pour qu'une barre et son repère à deux chiffres ("10.11") restent lisibles. */
    private const GRAPHIQUE_PAR_RANGEE = 16;

    private const GRAPHIQUE_COLONNE_PC = 100 / self::GRAPHIQUE_PAR_RANGEE;

    /** Largeur de la zone de contenu de la page (A4 210mm − marges 10mm de chaque côté, cf. MpdfFactory) : sert à donner à chaque barre une largeur explicite en mm plutôt qu'un pourcentage, qui ne se propage pas de façon fiable à une table imbriquée dans mPDF. */
    private const PAGE_CONTENU_MM = 190;

    /**
     * Diagramme à bandes maison : mPDF ne peut pas exécuter de JS/canvas, donc
     * pas de vraie librairie de graphiques. Deux limites du moteur dictent la
     * construction :
     * - un fond posé sur une <div> ne s'affiche pas de façon fiable ; un
     *   <td> avec un fond, lui, s'affiche (les en-têtes verts de tous les
     *   tableaux du document le prouvent) — la barre est donc portée par un
     *   <td>, pas par une <div> ;
     * - la hauteur d'une ligne de tableau est partagée par toutes ses
     *   cellules (comme dans n'importe quel moteur HTML) : mettre les barres
     *   de tailles différentes sur une même ligne les forcerait toutes à la
     *   hauteur de la plus grande. Chaque colonne porte donc sa propre
     *   mini-table à deux lignes (espaceur transparent, puis barre) plutôt
     *   que de partager une ligne avec ses voisines — et cette mini-table
     *   reçoit une largeur explicite en mm (pas en %), une table imbriquée
     *   ne respectant pas de façon fiable un `width:100%` hérité de sa
     *   cellule parente.
     *
     * Une pyramide sur tout un établissement peut comporter des dizaines de
     * tranches d'âge exact (années.mois) : les caser toutes dans une seule
     * rangée les rendrait illisibles (colonnes de moins d'un millimètre),
     * d'où le découpage en plusieurs rangées de largeur fixe.
     *
     * @param  list<array{age: string, total: int}>  $lignes
     */
    private function graphique(array $lignes): string
    {
        if ($lignes === []) {
            return '';
        }

        $maxTotal = max(array_column($lignes, 'total')) ?: 1;
        $hauteurMaxMm = 32;
        $largeurBarreMm = round(self::PAGE_CONTENU_MM / self::GRAPHIQUE_PAR_RANGEE - 0.6, 2);

        $html = '';
        foreach (array_chunk($lignes, self::GRAPHIQUE_PAR_RANGEE) as $rangee) {
            $barres = '';
            foreach ($rangee as $ligne) {
                $hauteur = $ligne['total'] > 0 ? max(1, round($ligne['total'] / $maxTotal * $hauteurMaxMm, 1)) : 0;
                $espaceur = round($hauteurMaxMm - $hauteur, 1);

                $barres .= '<td>'
                    .'<div class="valeur">'.($ligne['total'] > 0 ? $ligne['total'] : '').'</div>'
                    .'<table style="width:'.$largeurBarreMm.'mm;margin:0 auto;border-collapse:collapse;"><tr>'
                    .'<td style="width:'.$largeurBarreMm.'mm;height:'.$espaceur.'mm;padding:0;border:none;"></td>'
                    .'</tr><tr>'
                    .'<td class="barre" style="width:'.$largeurBarreMm.'mm;height:'.$hauteur.'mm;padding:0;border:none;"></td>'
                    .'</tr></table>'
                    .'<div class="repere">'.$this->e($ligne['age']).'</div>'
                    .'</td>';
            }

            $html .= '<table class="no-border graphique"><tr>'.$barres.'</tr></table>';
        }

        return $html;
    }

    private function titre(string $perimetre): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Pyramide des âges</span><br>'
            .'<span class="titre-en">Age pyramid</span>'
            .'</div>'
            .'<div class="bandeau">'.$this->e($perimetre).'</div>';
    }

    /** @param  list<array{age: string, garcons: int, filles: int, total: int}>  $lignes */
    private function tableau(array $lignes): string
    {
        $corps = '';
        $totaux = ['garcons' => 0, 'filles' => 0, 'total' => 0];

        foreach ($lignes as $ligne) {
            $corps .= '<tr>'
                .'<td>'.$this->e($ligne['age']).'</td>'
                .'<td>'.$ligne['garcons'].'</td>'
                .'<td>'.$ligne['filles'].'</td>'
                .'<td>'.$ligne['total'].'</td>'
                .'</tr>';
            $totaux['garcons'] += $ligne['garcons'];
            $totaux['filles'] += $ligne['filles'];
            $totaux['total'] += $ligne['total'];
        }

        if ($corps === '') {
            $corps = '<tr><td colspan="4" style="padding:6mm;">Aucun élève avec une date de naissance renseignée.</td></tr>';
        }

        return '<table class="ages"><thead><tr>'
            .'<th style="width:25%;">Âge exact (ans.mois)<br><i>Exact age (years.months)</i></th>'
            .'<th style="width:25%;">Garçons<br><i>Boys</i></th>'
            .'<th style="width:25%;">Filles<br><i>Girls</i></th>'
            .'<th style="width:25%;">Effectif<br><i>Total</i></th>'
            .'</tr></thead><tbody>'.$corps.'</tbody>'
            .'<tfoot><tr><td>Total</td><td>'.$totaux['garcons'].'</td><td>'.$totaux['filles'].'</td><td>'.$totaux['total'].'</td></tr></tfoot>'
            .'</table>';
    }
}
