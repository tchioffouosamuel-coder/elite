<?php

namespace App\Support\Pdf;

use App\Models\Classe;
use App\Services\EmploiDuTempsService;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Carbon;
use Mpdf\Output\Destination;

/**
 * Fiche d'appel hebdomadaire remplie : la reconstitution numérique du
 * document papier tenu par les surveillants (une ligne par élève, une
 * colonne par période de cours de la semaine), avec les absences déjà
 * relevées via l'appel plutôt qu'à cocher à la main — {@see
 * \App\Services\EmploiDuTempsService::ficheAppelHebdomadaire()} fournit la
 * grille, ce générateur ne fait que la mettre en page.
 */
class FicheAppelHebdomadaireGenerator
{
    use RenduDocument;

    private const JOURS_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

    public function build(Classe $classe, array $grille): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 6,
            'margin_right' => 6,
        ], $classe->school);
        $mpdf->SetTitle("Fiche d'appel — {$classe->nom}");

        $html = $this->enTeteEcole($classe->school)
            .$this->titre($classe, $grille['jours'])
            .$this->tableau($grille);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'.$html.'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.fiche th{font-size:1.9mm;padding:0.5mm}'
            .'.fiche td{font-size:2mm;padding:0.8mm 0.4mm;height:4.5mm}'
            .'.fiche .nom{text-align:left;font-weight:bold;text-transform:uppercase;font-size:2.1mm}'
            .'.fiche .num{width:4%;}'
            .'.fiche .absent{background:#e05353;color:#fff;font-weight:bold}'
            .'.fiche .vide{background:#f0f0ee}'
            .'.fiche .total{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold}'
            .'.fiche thead .jour{background-color:'.self::ARDOISE.'}';
    }

    /** @param  list<Carbon>  $jours */
    private function titre(Classe $classe, array $jours): string
    {
        $debut = $jours[0]->format('d/m/Y');
        $fin = end($jours)->format('d/m/Y');

        return '<div style="text-align:center;line-height:1.4;margin-bottom:2mm;">'
            .'<span class="titre">Fiche d\'appel hebdomadaire — '.$this->e($classe->nom).'</span><br>'
            .'<span class="titre-en">Weekly attendance sheet</span><br>'
            .'<span class="mini">Semaine du '.$debut.' au '.$fin.'</span>'
            .'</div>';
    }

    /**
     * @param  array{jours: list<Carbon>, lignes: \Illuminate\Support\Collection}  $grille
     */
    private function tableau(array $grille): string
    {
        $periodes = EmploiDuTempsService::PERIODES_PAR_JOUR;

        $html = '<table class="fiche"><thead>';

        // Ligne 1 : nom du jour, une cellule fusionnée sur ses périodes.
        $html .= '<tr><th class="num" rowspan="2">N°</th><th class="nom" rowspan="2">Nom et prénoms</th>';
        foreach (self::JOURS_LABELS as $i => $label) {
            $date = $grille['jours'][$i]->format('d/m');
            $html .= '<th class="jour" colspan="'.$periodes.'" style="color:#fff;">'.$label.' '.$date.'</th>';
        }
        $html .= '<th rowspan="2">TOT<br>ABS</th></tr>';

        // Ligne 2 : numéro de période, 1 à 8, sous chaque jour.
        $html .= '<tr>';
        foreach (self::JOURS_LABELS as $label) {
            for ($p = 1; $p <= $periodes; $p++) {
                $html .= '<th>'.$p.'</th>';
            }
        }
        $html .= '</tr></thead><tbody>';

        $rang = 1;
        foreach ($grille['lignes'] as $ligne) {
            $html .= '<tr><td class="num">'.$rang++.'</td><td class="nom">'.$this->e($ligne['eleve']->nom_complet).'</td>';

            foreach ($grille['jours'] as $jour) {
                $periodesDuJour = $ligne['jours'][$jour->toDateString()] ?? [];

                for ($p = 0; $p < $periodes; $p++) {
                    if (! array_key_exists($p, $periodesDuJour)) {
                        // Pas de séance à ce rang ce jour-là — pas d'absence à y relever.
                        $html .= '<td class="vide"></td>';

                        continue;
                    }

                    $html .= $periodesDuJour[$p]
                        ? '<td class="absent">X</td>'
                        : '<td></td>';
                }
            }

            $html .= '<td class="total">'.$ligne['total_absences'].'</td></tr>';
        }

        if ($grille['lignes']->isEmpty()) {
            $colonnes = 3 + count(self::JOURS_LABELS) * $periodes;

            return $html.'<tr><td colspan="'.$colonnes.'" style="padding:6mm;">Aucun élève actif dans cette classe.</td></tr></tbody></table>';
        }

        return $html.'</tbody></table>'
            .'<div class="legende" style="margin-top:2mm;"><span class="absent" style="padding:0.5mm 2mm;">X</span> = absent à cette période — <i>absent at this period</i></div>';
    }
}
