<?php

namespace App\Services;

use App\Models\ClasseMatiere;
use App\Models\Note;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Saisie des notes du primaire et de la maternelle.
 *
 * Là où le secondaire saisit une note par élève et par séquence, archange
 * (`fr/users/marks.php`) présente pour une matière la grille complète du
 * trimestre : une colonne par séquence, un bloc de colonnes par volet
 * d'évaluation (oral, écrit, savoir-être, et pratique si la matière s'y prête).
 */
class NotePrimaireService extends BaseService
{
    /**
     * Grille de saisie d'une matière pour tout le trimestre.
     *
     * @return array{
     *     composantes: list<string>,
     *     sequences: list<array{id:int, libelle:string}>,
     *     bareme: int,
     *     lignes: Collection<int, array{eleve_id:int, nom_complet:string, notes: array<string, array<int, ?float>>}>
     * }
     */
    public function grille(ClasseMatiere $classeMatiere, Trimestre $trimestre): array
    {
        $composantes = $classeMatiere->matiere->composantes();
        $sequences = $trimestre->sequencesRetenues();

        $notes = Note::where('classe_matiere_id', $classeMatiere->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get();

        $lignes = $classeMatiere->classe->eleves()->where('statut', 'actif')->orderBy('nom')->get()
            ->map(function ($eleve) use ($notes, $composantes, $sequences) {
                $parEleve = $notes->where('eleve_id', $eleve->id);

                $valeurs = [];
                foreach ($composantes as $composante) {
                    foreach ($sequences as $sequence) {
                        $note = $parEleve->first(
                            fn (Note $n) => $n->composante === $composante && $n->sequence_id === $sequence->id
                        );
                        $valeurs[$composante][$sequence->id] = $note?->valeur !== null ? (float) $note->valeur : null;
                    }
                }

                return [
                    'eleve_id' => $eleve->id,
                    'nom_complet' => $eleve->nomComplet(),
                    'notes' => $valeurs,
                ];
            });

        return [
            'composantes' => $composantes,
            'sequences' => $sequences->map(fn ($s) => ['id' => $s->id, 'libelle' => $s->libelle])->values()->all(),
            'bareme' => (int) ($classeMatiere->matiere->notation ?? 20),
            'lignes' => $lignes,
        ];
    }

    /**
     * Enregistre la grille complète : chaque entrée cible un élève, une
     * séquence et un volet.
     *
     * @param  array<int, array{eleve_id:int, sequence_id:int, composante:string, valeur: ?float}>  $notes
     */
    public function sauvegarderEnLot(ClasseMatiere $classeMatiere, array $notes, ?User $user): int
    {
        $personnelId = $user?->personnel?->id;
        $eleveIdsValides = $classeMatiere->classe->eleves()->pluck('id')->flip();
        $composantesValides = array_flip($classeMatiere->matiere->composantes());
        $sequenceIdsValides = $classeMatiere->classe->anneeScolaire
            ? Sequence::whereHas(
                'trimestre',
                fn ($q) => $q->where('annee_scolaire_id', $classeMatiere->classe->annee_scolaire_id)
            )->pluck('id')->flip()
            : collect();

        return $this->transaction(function () use ($classeMatiere, $notes, $personnelId, $eleveIdsValides, $composantesValides, $sequenceIdsValides) {
            $count = 0;

            foreach ($notes as $row) {
                // Défense en profondeur : un élève d'une autre classe, une séquence
                // d'une autre année ou un volet que la matière n'évalue pas sont
                // ignorés plutôt que d'être écrits en base.
                if (! $eleveIdsValides->has($row['eleve_id'])
                    || ! isset($composantesValides[$row['composante']])
                    || ! $sequenceIdsValides->has($row['sequence_id'])) {
                    continue;
                }

                Note::updateOrCreate(
                    [
                        'eleve_id' => $row['eleve_id'],
                        'classe_matiere_id' => $classeMatiere->id,
                        'sequence_id' => $row['sequence_id'],
                        'composante' => $row['composante'],
                    ],
                    ['valeur' => $row['valeur'] ?? null, 'saisi_par' => $personnelId]
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Au primaire, c'est le titulaire de la classe qui saisit toutes les
     * matières — contrairement au secondaire où chaque enseignant ne saisit
     * que la sienne.
     */
    public function peutSaisir(User $user, ClasseMatiere $classeMatiere): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_etablissement', 'censeur_sg'])) {
            return true;
        }

        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return false;
        }

        return $classeMatiere->classe->titulaire_id === $personnelId
            || $classeMatiere->personnel_id === $personnelId;
    }
}
