<?php

namespace App\Services;

use App\Models\ClasseMatiere;
use App\Models\Evaluation;
use Illuminate\Support\Collection;

/**
 * Préparation des évaluations : questions, barème et compétences visées.
 *
 * Comme le programme, chaque évaluation se réédite d'un bloc — questions
 * comprises — plutôt que par des appels unitaires : une évaluation compte
 * rarement plus d'une dizaine de questions, diffuser leur édition n'apporte
 * rien et complique l'écran.
 */
class EvaluationService extends BaseService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function liste(ClasseMatiere $classeMatiere): Collection
    {
        return Evaluation::where('classe_matiere_id', $classeMatiere->id)
            ->with(['questions', 'progressionItem', 'creePar'])
            ->orderByDesc('date_prevue')->orderByDesc('id')
            ->get()
            ->map($this->presenter(...));
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  array<int, array{id?: int, enonce: string, bareme_question: int}>  $questions
     */
    public function creer(ClasseMatiere $classeMatiere, array $donnees, array $questions, ?int $personnelId): array
    {
        return $this->transaction(function () use ($classeMatiere, $donnees, $questions, $personnelId) {
            $evaluation = Evaluation::create([
                ...$donnees,
                'school_id' => $classeMatiere->classe->school_id,
                'classe_matiere_id' => $classeMatiere->id,
                'cree_par' => $personnelId,
            ]);

            $this->remplacerQuestions($evaluation, $questions);

            return $this->presenter($evaluation->load('questions', 'progressionItem', 'creePar'));
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  array<int, array{id?: int, enonce: string, bareme_question: int}>  $questions
     */
    public function modifier(Evaluation $evaluation, array $donnees, array $questions): array
    {
        return $this->transaction(function () use ($evaluation, $donnees, $questions) {
            $evaluation->update($donnees);
            $this->remplacerQuestions($evaluation, $questions);

            return $this->presenter($evaluation->load('questions', 'progressionItem', 'creePar'));
        });
    }

    /**
     * @param  array<int, array{id?: int, enonce: string, bareme_question: int}>  $questions
     */
    private function remplacerQuestions(Evaluation $evaluation, array $questions): void
    {
        $conserves = [];

        foreach (array_values($questions) as $ordre => $question) {
            $attributs = [
                'evaluation_id' => $evaluation->id,
                'enonce' => $question['enonce'],
                'bareme_question' => $question['bareme_question'],
                'ordre' => $ordre + 1,
            ];

            $item = isset($question['id'])
                ? $evaluation->questions()->find($question['id'])
                : null;

            $item = $item ? tap($item)->update($attributs) : $evaluation->questions()->create($attributs);
            $conserves[] = $item->id;
        }

        $evaluation->questions()->whereNotIn('id', $conserves ?: [0])->delete();
    }

    /** @return array<string, mixed> */
    private function presenter(Evaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'titre' => $evaluation->titre,
            'type' => $evaluation->type,
            'date_prevue' => $evaluation->date_prevue?->format('Y-m-d'),
            'bareme' => $evaluation->bareme,
            'competences' => $evaluation->competences,
            'progression_item_id' => $evaluation->progression_item_id,
            'lecon' => $evaluation->progressionItem?->titre,
            'cree_par' => $evaluation->creePar?->nom_complet,
            'bareme_questions' => $evaluation->questions->sum('bareme_question'),
            'questions' => $evaluation->questions->map(fn ($q) => [
                'id' => $q->id,
                'enonce' => $q->enonce,
                'bareme_question' => $q->bareme_question,
            ])->values(),
        ];
    }
}
