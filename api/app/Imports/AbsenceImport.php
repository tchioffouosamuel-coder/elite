<?php

namespace App\Imports;

use App\Exports\AbsenceModeleExport;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) : matricule, heures
 * justifiées, heures non justifiées — mêmes libellés que
 * {@see AbsenceModeleExport}.
 *
 * Une ligne dont le matricule ne correspond à aucun élève de la classe est
 * ignorée (pas d'erreur bloquante, même tolérance que {@see NoteImport}), de
 * même qu'une ligne dont les deux colonnes d'heures sont vides : importer un
 * fichier qui couvre toute l'école sans avoir touché une classe ne doit rien
 * y effacer.
 *
 * Ne touche pas la base : les lignes reconnues sont collectées dans
 * {@see $lignes}, pour que `DisciplineService::importerAbsencesDepuisExcel()`
 * puisse détecter un remplacement d'une correction déjà saisie avant d'écrire
 * quoi que ce soit.
 */
class AbsenceImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    /** @var list<array{eleve_id:int, heures_justifiees:float, heures_non_justifiees:float}> */
    public array $lignes = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly Collection $eleveIdsValides,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['matricule'])) {
                continue;
            }

            $justifiees = $row['heures_justifiees'] ?? null;
            $nonJustifiees = $row['heures_non_justifiees'] ?? null;

            if (($justifiees === null || $justifiees === '') && ($nonJustifiees === null || $nonJustifiees === '')) {
                continue;
            }

            $eleveId = Eleve::where('school_id', $this->schoolId)->where('matricule', trim((string) $row['matricule']))->value('id');

            if (! $eleveId || ! $this->eleveIdsValides->has($eleveId)) {
                continue;
            }

            $this->lignes[] = [
                'eleve_id' => $eleveId,
                'heures_justifiees' => $justifiees !== null && $justifiees !== '' ? (float) $justifiees : 0.0,
                'heures_non_justifiees' => $nonJustifiees !== null && $nonJustifiees !== '' ? (float) $nonJustifiees : 0.0,
            ];
        }
    }

    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string'],
            'heures_justifiees' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'heures_non_justifiees' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
