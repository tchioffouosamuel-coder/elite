<?php

namespace App\Imports;

use App\Models\Eleve;
use App\Models\Note;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) : matricule, note
 * Une ligne dont le matricule ne correspond à aucun élève de l'école (ou de
 * la classe de l'affectation visée) est simplement ignorée — pas d'erreur
 * bloquante, pour tolérer un fichier contenant toute l'école sur un onglet
 * "classe" filtré manuellement par l'utilisateur.
 */
class NoteImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    public function __construct(
        private readonly int $schoolId,
        private readonly int $classeMatiereId,
        private readonly int $sequenceId,
        private readonly ?int $personnelId,
        private readonly \Illuminate\Support\Collection $eleveIdsValides,
    ) {
    }

    public function collection(\Illuminate\Support\Collection $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['matricule']) || $row['note'] === null || $row['note'] === '') {
                continue;
            }

            $eleveId = Eleve::where('school_id', $this->schoolId)->where('matricule', trim((string) $row['matricule']))->value('id');

            if (! $eleveId || ! $this->eleveIdsValides->has($eleveId)) {
                continue;
            }

            Note::updateOrCreate(
                ['eleve_id' => $eleveId, 'classe_matiere_id' => $this->classeMatiereId, 'sequence_id' => $this->sequenceId],
                ['valeur' => $row['note'], 'saisi_par' => $this->personnelId]
            );

            $this->importedCount++;
        }
    }

    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string'],
            'note' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }
}
