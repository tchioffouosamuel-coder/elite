<?php

namespace App\Support\Pdf;

use App\Models\ClasseMatiere;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Fiche de progression au format du gabarit de l'établissement — un document
 * par matière/classe, A4 paysage : la table compte assez de colonnes (jusqu'à
 * quinze fixes, plus dix libres) pour qu'un rendu portrait la rende
 * illisible.
 *
 * Il existe deux gabarits, maternelle/primaire et secondaire, qui partagent
 * l'essentiel de leurs colonnes — `colonnesPour()` porte la seule différence
 * qui compte pour ce document.
 */
class ProgressionFicheGenerator
{
    use RenduDocument;

    /**
     * @param  Collection<int, ProgressionItem>  $lecons
     * @param  Collection<int, ProgressionColonne>  $colonnes
     */
    public function build(
        ClasseMatiere $classeMatiere,
        Collection $lecons,
        Collection $colonnes,
        string $cycle,
        ?string $terme,
        ?string $anneeScolaire,
    ): string {
        $school = $classeMatiere->classe->school;

        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 8,
            'margin_bottom' => 10,
            'margin_left' => 6,
            'margin_right' => 6,
        ], $school);
        $mpdf->SetTitle('Fiche de progression — '.$classeMatiere->classe->nom.' — '.$classeMatiere->matiere->nom);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .$this->titre($cycle)
                .$this->cartouche($classeMatiere, $cycle, $terme, $anneeScolaire)
                .$this->legende()
                .$this->tableau($lecons, $colonnes, $cycle)
                .$this->pied($classeMatiere)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.fiche th{font-size:2.1mm;padding:1mm 0.6mm}'
            .'.fiche td{font-size:2mm;padding:0.9mm 0.6mm;text-align:left;vertical-align:top;line-height:1.3}'
            .'.fiche tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.fiche .centre{text-align:center}'
            .'.cartouche{width:100%;table-layout:fixed;margin:2mm 0 3mm}'
            .'.cartouche td{border:none;text-align:left;padding:0.6mm 2mm;font-size:2.6mm;vertical-align:top}'
            .'.cartouche .libelle{font-weight:bold;color:'.self::ARDOISE.';white-space:nowrap}'
            .'.cartouche .valeur{border-bottom:0.4px solid #999;display:inline-block;min-width:70%;}'
            .'.legende{display:block;text-align:center;font-size:2mm;font-style:italic;color:#777;margin-bottom:1.5mm}';
    }

    private function titre(string $cycle): string
    {
        $sousTitre = $cycle === 'secondaire' ? 'Secondary / TVET' : 'Nursery & Primary School';

        return '<div style="text-align:center;line-height:1.35;">'
            .'<span class="titre">Fiche de progression</span><br>'
            .'<span class="titre-en">Scheme of Work / Progression Sheet — '.$this->e($sousTitre).'</span>'
            .'</div>';
    }

    private function legende(): string
    {
        return '<span class="legende">Une ligne = une leçon. <i>One row = one lesson.</i></span>';
    }

    /**
     * Cartouche : School/Teacher toujours, puis Department/Specialty et
     * Module/Competency au secondaire seulement — le primaire n'en a pas
     * dans son gabarit, juste Class/Subject.
     */
    private function cartouche(ClasseMatiere $cm, string $cycle, ?string $terme, ?string $anneeScolaire): string
    {
        $enseignant = $cm->enseignant?->nom_complet ?? $cm->classe->titulaire?->nom_complet ?? '—';
        $annee = $anneeScolaire ?? '—';
        $termeAffiche = $terme ?? 'Année scolaire complète';

        $lignes = [
            ['School', $cm->classe->school->name, 'Teacher', $enseignant],
        ];

        if ($cycle === 'secondaire') {
            $lignes[] = ['Department', $cm->matiere->departement?->nom ?? '—', 'Specialty', $cm->specialite ?? '—'];
            $lignes[] = ['Class', $cm->classe->nom, 'Module / Competency', $cm->module_competence ?? '—'];
        } else {
            $lignes[] = ['Class', $cm->classe->nom, 'Subject', $cm->matiere->nom];
        }

        $lignes[] = ['Academic Year', $annee, 'Term', $termeAffiche];

        $html = '<table class="cartouche">';

        foreach ($lignes as [$libelle1, $valeur1, $libelle2, $valeur2]) {
            $html .= '<tr>'
                .'<td style="width:12%;" class="libelle">'.$this->e($libelle1).'</td>'
                .'<td style="width:38%;"><span class="valeur">'.$this->e($valeur1).'</span></td>'
                .'<td style="width:16%;" class="libelle">'.$this->e($libelle2).'</td>'
                .'<td style="width:34%;"><span class="valeur">'.$this->e($valeur2).'</span></td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    /**
     * Colonnes du tableau dans l'ordre du gabarit — {clé, libellé, poids}. Le
     * poids détermine la largeur relative de la colonne : les repères courts
     * (semaine, dates, durée) valent 1, les zones de texte valent 2.
     *
     * @return list<array{cle: string, libelle: string, poids: int}>
     */
    private function colonnesPour(string $cycle): array
    {
        $communes1 = [
            ['cle' => 'semaine', 'libelle' => 'Week', 'poids' => 1],
            ['cle' => 'date_prevue', 'libelle' => 'Date Planned', 'poids' => 1],
            ['cle' => 'date_realisee', 'libelle' => 'Date Taught', 'poids' => 1],
            ['cle' => 'duree', 'libelle' => $cycle === 'secondaire' ? 'Periods' : 'Duration', 'poids' => 1],
            ['cle' => 'topic', 'libelle' => 'Topic', 'poids' => 2],
            ['cle' => 'sous_topic', 'libelle' => 'Sub-topic', 'poids' => 2],
        ];

        // Competency n'existe que sur le gabarit primaire ; Teaching Strategy
        // que sur le secondaire — jamais les deux à la fois sur une même fiche.
        $specifique = $cycle === 'secondaire'
            ? []
            : [['cle' => 'competence', 'libelle' => 'Competency', 'poids' => 2]];

        $communes2 = [
            ['cle' => 'expected_learning_outcomes', 'libelle' => 'Learning Outcomes', 'poids' => 2],
            ['cle' => 'entry_behaviour', 'libelle' => 'Entry Behaviour', 'poids' => 2],
            ['cle' => 'teaching_aids', 'libelle' => $cycle === 'secondaire' ? 'Resources / Teaching Aids' : 'Teaching Aids', 'poids' => 2],
        ];

        if ($cycle === 'secondaire') {
            $communes2[] = ['cle' => 'teaching_learning_strategies', 'libelle' => 'Teaching / Strategy', 'poids' => 2];
        }

        $fin = [
            ['cle' => 'facilitators_activities', 'libelle' => "Teacher's Activities", 'poids' => 2],
            ['cle' => 'learners_activities', 'libelle' => "Learners' Activities", 'poids' => 2],
            ['cle' => 'assessment', 'libelle' => 'Assessment', 'poids' => 2],
            ['cle' => 'assignment', 'libelle' => 'Assignment', 'poids' => 1],
            ['cle' => 'remarks', 'libelle' => 'Remarks', 'poids' => 1],
        ];

        return [...$communes1, ...$specifique, ...$communes2, ...$fin];
    }

    /**
     * @param  Collection<int, ProgressionItem>  $lecons
     * @param  Collection<int, ProgressionColonne>  $colonnes
     */
    private function tableau(Collection $lecons, Collection $colonnes, string $cycle): string
    {
        $definitions = $this->colonnesPour($cycle);
        $poidsColonnesLibres = 1;
        $poidsTotal = collect($definitions)->sum('poids') + $colonnes->count() * $poidsColonnesLibres;

        $largeur = fn (int $poids) => round($poids / $poidsTotal * 100, 2);

        $entetes = '';
        foreach ($definitions as $def) {
            $entetes .= '<th style="width:'.$largeur($def['poids']).'%;">'.$this->e($def['libelle']).'</th>';
        }
        foreach ($colonnes as $colonne) {
            $entetes .= '<th style="width:'.$largeur($poidsColonnesLibres).'%;">'.$this->e($colonne->libelle).'</th>';
        }

        $corps = '';

        foreach ($lecons->values() as $index => $lecon) {
            $corps .= '<tr>';

            foreach ($definitions as $def) {
                $corps .= '<td'.($def['cle'] === 'semaine' ? ' class="centre"' : '').'>'
                    .$this->valeurCellule($lecon, $def['cle'], $index)
                    .'</td>';
            }

            foreach ($colonnes as $colonne) {
                $valeur = $lecon->colonnes_libres[$colonne->id] ?? null;
                $corps .= '<td>'.($valeur !== null ? nl2br($this->e($valeur)) : '').'</td>';
            }

            $corps .= '</tr>';
        }

        if ($corps === '') {
            $colspan = count($definitions) + $colonnes->count();
            $corps = '<tr><td colspan="'.$colspan.'" style="text-align:center;padding:6mm;">Aucune leçon programmée.</td></tr>';
        }

        return '<table class="fiche"><thead><tr>'.$entetes.'</tr></thead><tbody>'.$corps.'</tbody></table>';
    }

    private function valeurCellule(ProgressionItem $lecon, string $cle, int $index): string
    {
        // La semaine se remplit d'elle-même si l'enseignant ne l'a pas saisie
        // : le gabarit numérote ses lignes en continu.
        if ($cle === 'semaine') {
            return $this->e($lecon->semaine ?: (string) ($index + 1));
        }

        if (in_array($cle, ['date_prevue', 'date_realisee'], true)) {
            $date = $lecon->{$cle};

            return $date ? $date->format('d/m/Y') : '';
        }

        $valeur = $lecon->{$cle};

        return $valeur !== null ? nl2br($this->e($valeur)) : '';
    }

    private function pied(ClasseMatiere $cm): string
    {
        $enseignant = $cm->enseignant?->nom_complet ?? $cm->classe->titulaire?->nom_complet;
        $ville = trim(explode(',', (string) $cm->classe->school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:33%;font-size:2.6mm;vertical-align:top;">'
            .'<b>Teacher</b>'.($enseignant ? ' — '.$this->e($enseignant) : '').'<br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature</span>'
            .'</td>'
            .'<td class="no-border" style="width:34%;"></td>'
            .'<td class="no-border" style="width:33%;text-align:center;font-size:2.6mm;vertical-align:top;">'
            .'Fait à '.$this->e($ville !== '' ? $ville : '…………').', le '.date('d/m/Y').'<br>'
            .'<b>Head of Department / Principal</b><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            .'</td></tr></table>';
    }
}
