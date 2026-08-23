<?php

namespace App\Imports;

use App\Models\Classe;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) : nom, sigle, capacite.
 *
 * Le niveau, le sous-système, l'enseignant, etc. ne font pas partie de
 * l'import : ils s'affectent après coup, classe par classe.
 */
class ClasseImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public int $importedCount = 0;

    public function __construct(private readonly int $schoolId)
    {
    }

    public function model(array $row): ?Classe
    {
        $this->importedCount++;

        return new Classe([
            'school_id' => $this->schoolId,
            'nom' => $row['nom'],
            'sigle' => $row['sigle'] ?? null,
            'capacite' => $row['capacite'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string'],
        ];
    }
}
