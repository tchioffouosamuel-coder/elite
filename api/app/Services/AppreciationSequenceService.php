<?php

namespace App\Services;

use App\Models\AppreciationSequence;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Point 16 de la punch-list : une appréciation courte (60 caractères) par
 * élève, pour la matière (secondaire) ou la compétence (primaire,
 * maternelle) de l'enseignant, à la séquence 1 ou 2 d'un trimestre — les deux
 * seules que le bulletin du premier trimestre affiche ensemble (cf.
 * `BulletinService`/`BulletinPrimaireService`).
 *
 * Même découpage secondaire/primaire que {@see NoteService} et
 * {@see NotePrimaireService}, dont on réutilise ici le périmètre (`peutSaisir`)
 * plutôt que de le redéfinir : qui peut noter une matière ou une compétence
 * peut y laisser une appréciation.
 */
class AppreciationSequenceService extends BaseService
{
    /** Seules les deux premières séquences d'un trimestre portent une appréciation. */
    private const ORDRES_AUTORISES = [1, 2];

    /**
     * @return Collection<int, array{eleve_id:int, nom_complet:string, id: ?int, texte: ?string}>
     */
    public function grille(ClasseMatiere $classeMatiere, int $sequenceId): Collection
    {
        $appreciations = AppreciationSequence::where('classe_matiere_id', $classeMatiere->id)
            ->where('sequence_id', $sequenceId)
            ->get()
            ->keyBy('eleve_id');

        return $this->ligneEleves($classeMatiere->classe->eleves(), $appreciations);
    }

    /**
     * @return Collection<int, array{eleve_id:int, nom_complet:string, id: ?int, texte: ?string}>
     */
    public function grillePrimaire(ClasseCompetence $classeCompetence, int $sequenceId): Collection
    {
        $appreciations = AppreciationSequence::where('classe_competence_id', $classeCompetence->id)
            ->where('sequence_id', $sequenceId)
            ->get()
            ->keyBy('eleve_id');

        return $this->ligneEleves($classeCompetence->classe->eleves(), $appreciations);
    }

    private function ligneEleves($elevesQuery, Collection $appreciations): Collection
    {
        return $elevesQuery->where('statut', 'actif')->inscritAnneeActive()->orderBy('nom_complet')->get()
            ->map(fn ($eleve) => [
                'eleve_id' => $eleve->id,
                'nom_complet' => $eleve->nom_complet,
                'id' => $appreciations->get($eleve->id)?->id,
                'texte' => $appreciations->get($eleve->id)?->texte,
            ]);
    }

    /**
     * @param  array<int, array{eleve_id:int, texte: ?string}>  $appreciations
     */
    public function sauvegarderEnLot(ClasseMatiere $classeMatiere, int $sequenceId, array $appreciations, ?User $user): int
    {
        $eleveIdsValides = $classeMatiere->classe->eleves()->pluck('id')->flip();

        return $this->enregistrer($sequenceId, $appreciations, $user, $eleveIdsValides, $classeMatiere->classe->school_id,
            fn (int $eleveId, ?string $texte, ?int $personnelId) => AppreciationSequence::updateOrCreate(
                ['eleve_id' => $eleveId, 'classe_matiere_id' => $classeMatiere->id, 'sequence_id' => $sequenceId],
                ['texte' => $texte, 'saisi_par' => $personnelId],
            ));
    }

    /**
     * @param  array<int, array{eleve_id:int, texte: ?string}>  $appreciations
     */
    public function sauvegarderEnLotPrimaire(ClasseCompetence $classeCompetence, int $sequenceId, array $appreciations, ?User $user): int
    {
        $eleveIdsValides = $classeCompetence->classe->eleves()->pluck('id')->flip();

        return $this->enregistrer($sequenceId, $appreciations, $user, $eleveIdsValides, $classeCompetence->classe->school_id,
            fn (int $eleveId, ?string $texte, ?int $personnelId) => AppreciationSequence::updateOrCreate(
                ['eleve_id' => $eleveId, 'classe_competence_id' => $classeCompetence->id, 'sequence_id' => $sequenceId],
                ['texte' => $texte, 'saisi_par' => $personnelId],
            ));
    }

    /**
     * @param  array<int, array{eleve_id:int, texte: ?string}>  $appreciations
     */
    private function enregistrer(int $sequenceId, array $appreciations, ?User $user, Collection $eleveIdsValides, int $schoolId, \Closure $ecrire): int
    {
        $personnelId = $user?->personnel?->id;

        return $this->transaction(function () use ($sequenceId, $appreciations, $user, $eleveIdsValides, $schoolId, $personnelId, $ecrire) {
            $sequence = Sequence::with('trimestre.anneeScolaire')->lockForUpdate()->findOrFail($sequenceId);

            if ($user) {
                $sequence->verifierSaisieNotes($user, $schoolId);
            }

            // Seules les séquences 1 et 2 d'un trimestre portent une
            // appréciation : la troisième (ou plus, selon `num_sequences`)
            // n'apparaît jamais sur le bulletin du premier trimestre.
            abort_unless(in_array($sequence->ordre, self::ORDRES_AUTORISES, true), 422,
                "L'appréciation de séquence ne se saisit que sur la 1re ou la 2e séquence d'un trimestre.");

            $count = 0;
            foreach ($appreciations as $row) {
                if (! $eleveIdsValides->has($row['eleve_id'])) {
                    continue;
                }

                $texte = isset($row['texte']) && $row['texte'] !== '' ? $row['texte'] : null;
                $ecrire($row['eleve_id'], $texte, $personnelId);
                $count++;
            }

            return $count;
        });
    }
}
