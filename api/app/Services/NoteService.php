<?php

namespace App\Services;

use App\Imports\NoteImport;
use App\Models\ClasseMatiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class NoteService extends BaseService
{
    /**
     * Grille de saisie : un élève actif de la classe par ligne, avec sa note
     * existante pour cette séquence si elle existe.
     *
     * @return Collection<int, array{eleve_id:int, nom_complet:string, note_id: ?int, valeur: ?float}>
     */
    public function grille(ClasseMatiere $classeMatiere, int $sequenceId): Collection
    {
        $notes = Note::where('classe_matiere_id', $classeMatiere->id)
            ->where('sequence_id', $sequenceId)
            ->where('composante', 'unique')
            ->get()
            ->keyBy('eleve_id');

        return $classeMatiere->classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get()
            ->map(fn ($eleve) => [
                'eleve_id' => $eleve->id,
                'nom_complet' => $eleve->nom_complet,
                'note_id' => $notes->get($eleve->id)?->id,
                'valeur' => $notes->get($eleve->id)?->valeur !== null ? (float) $notes->get($eleve->id)->valeur : null,
            ]);
    }

    /**
     * @param  array<int, array{eleve_id:int, valeur: ?float}>  $notes
     */
    public function sauvegarderEnLot(ClasseMatiere $classeMatiere, int $sequenceId, array $notes, ?User $user): int
    {
        $personnelId = $user?->personnel?->id;

        $eleveIdsValides = $classeMatiere->classe->eleves()->pluck('id')->flip();

        return $this->transaction(function () use ($classeMatiere, $sequenceId, $notes, $personnelId, $eleveIdsValides) {
            $count = 0;
            foreach ($notes as $row) {
                // L'école est déjà garantie par la validation scopée de la requête ;
                // ce filtre couvre le cas plus fin d'un élève d'une autre classe
                // de la même école glissé dans le lot.
                if (! $eleveIdsValides->has($row['eleve_id'])) {
                    continue;
                }

                Note::updateOrCreate(
                    [
                        'eleve_id' => $row['eleve_id'],
                        'classe_matiere_id' => $classeMatiere->id,
                        'sequence_id' => $sequenceId,
                        // Le secondaire n'a qu'une note par séquence : les volets
                        // (oral, écrit…) sont propres au primaire.
                        'composante' => 'unique',
                    ],
                    ['valeur' => $row['valeur'] ?? null, 'saisi_par' => $personnelId]
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * @return array{imported: int, failed: int, errors: array}
     */
    public function importFromExcel(int $schoolId, ClasseMatiere $classeMatiere, int $sequenceId, ?User $user, UploadedFile $file): array
    {
        $import = new NoteImport(
            $schoolId,
            $classeMatiere->id,
            $sequenceId,
            $user?->personnel?->id,
            $classeMatiere->classe->eleves()->pluck('id')->flip(),
        );

        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];
    }

    public function peutSaisir(User $user, ClasseMatiere $classeMatiere): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'])) {
            return true;
        }

        return $user->personnel && $classeMatiere->personnel_id === $user->personnel->id;
    }

    /** Part des élèves actifs de la classe ayant déjà une note pour cette séquence. */
    public function tauxRemplissage(ClasseMatiere $classeMatiere, int $sequenceId): int
    {
        $grille = $this->grille($classeMatiere, $sequenceId);

        if ($grille->isEmpty()) {
            return 0;
        }

        return (int) round($grille->filter(fn (array $ligne) => $ligne['valeur'] !== null)->count() / $grille->count() * 100);
    }
}
