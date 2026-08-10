<?php

namespace App\Imports;

use App\Models\Personnel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) :
 * nom, prenom, fonction, matricule, telephone, email, date_embauche
 */
class PersonnelImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public int $importedCount = 0;

    public function __construct(private readonly int $schoolId) {}

    public function model(array $row): ?Personnel
    {
        $this->importedCount++;

        return new Personnel([
            'school_id' => $this->schoolId,
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'fonction' => $row['fonction'],
            'matricule' => $row['matricule'] ?? null,
            'telephone' => $row['telephone'] ?? null,
            'email' => $row['email'] ?? null,
            'statut' => 'actif',
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string'],
            'prenom' => ['required', 'string'],
            'fonction' => ['required', 'string'],
            'email' => ['nullable', 'email'],
        ];
    }
}
