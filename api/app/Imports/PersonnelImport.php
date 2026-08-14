<?php

namespace App\Imports;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) :
 * nom_complet, fonction, matricule, telephone, email, date_embauche
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
            'nom_complet' => $row['nom_complet'],
            'fonction_id' => $this->fonctionId($row['fonction']),
            'matricule' => $row['matricule'] ?? null,
            'telephone' => $row['telephone'] ?? null,
            'email' => $row['email'] ?? null,
            'statut' => 'actif',
        ]);
    }

    /**
     * Resout un intitule du fichier vers le referentiel. Une fonction inconnue
     * y est ajoutee plutot que de faire echouer la ligne : l'import sert a
     * reprendre un existant, pas a le juger.
     */
    private function fonctionId(string $libelle): ?int
    {
        $libelle = trim($libelle);

        if ($libelle === '') {
            return null;
        }

        $fonction = FonctionReferentiel::forSchool($this->schoolId)
            ->whereRaw('LOWER(label_fr) = ?', [Str::lower($libelle)])
            ->first();

        return ($fonction ?: FonctionReferentiel::create([
            'school_id' => $this->schoolId,
            'label_fr' => $libelle,
        ]))->id;
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['required', 'string'],
            'fonction' => ['required', 'string'],
            'email' => ['nullable', 'email'],
        ];
    }
}
