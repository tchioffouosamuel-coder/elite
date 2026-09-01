<?php

namespace App\Support\Progression;

/**
 * Colonnes de la fiche de progression, dans l'ordre du gabarit de
 * l'établissement — source unique pour le modèle Excel téléchargeable
 * (`ProgressionModeleMatiereSheet`), afin que les en-têtes générés soient
 * relus sans erreur par `ProgressionImport` (dont la table `COLONNES` fixe le
 * texte attendu, une fois normalisé).
 *
 * Les libellés diffèrent parfois de ceux, plus courts, du PDF
 * (`ProgressionFicheGenerator::colonnesPour()`) : celui-ci n'a qu'à tenir
 * dans une cellule imprimée, quand celui-ci doit surtout survivre à
 * l'aller-retour import.
 */
class ProgressionGabaritColonnes
{
    /** @return array<string, string> champ du modèle => en-tête affiché */
    public static function pour(string $cycle): array
    {
        $communes1 = [
            'semaine' => 'Week',
            'date_prevue' => 'Date Planned',
            'date_realisee' => 'Date Taught',
            'duree' => $cycle === 'secondaire' ? 'Periods' : 'Duration',
            'topic' => 'Topic',
            'sous_topic' => 'Sub-topic',
        ];

        $specifique = $cycle === 'secondaire' ? [] : ['competence' => 'Competency'];

        $communes2 = [
            'expected_learning_outcomes' => 'Learning Outcomes',
            'entry_behaviour' => 'Entry Behaviour / Previous Knowledge',
            'teaching_aids' => $cycle === 'secondaire' ? 'Resources / Teaching Aids' : 'Teaching Aids',
        ];

        if ($cycle === 'secondaire') {
            $communes2['teaching_learning_strategies'] = 'Teaching / Strategy';
        }

        $fin = [
            'facilitators_activities' => "Teacher's Activities",
            'learners_activities' => "Learners' Activities",
            'assessment' => 'Assessment',
            'assignment' => 'Assignment',
            'remarks' => 'Remarks',
        ];

        return [...$communes1, ...$specifique, ...$communes2, ...$fin];
    }

    /** Ligne d'en-tête du gabarit : 7 (maternelle/primaire) ou 8 (secondaire), comme `ProgressionImport::headingRow()`. */
    public static function ligneEntete(string $cycle): int
    {
        return $cycle === 'secondaire' ? 8 : 7;
    }
}
