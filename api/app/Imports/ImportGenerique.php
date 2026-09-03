<?php

namespace App\Imports;

use App\Support\ImportExport\SpecificationModele;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Moteur d'import mécanique pour tout modèle décrit par une
 * {@see SpecificationModele} : normalisation des en-têtes (synonymes
 * tolérés, même logique que {@see EleveImport::COLONNES}), validation, puis
 * `updateOrCreate` — sans règle métier propre à un modèle en particulier.
 */
class ImportGenerique implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public function __construct(
        private readonly SpecificationModele $spec,
        private readonly int $schoolId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $data, int $index): array
    {
        $colonnes = $this->spec->colonnes();
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = $colonnes[$entete] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            $valeur = ($valeur === '') ? null : $valeur;

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        return $ligne;
    }

    public function collection(Collection $rows): void
    {
        $modele = $this->spec->modele();

        foreach ($rows as $row) {
            $ligne = $row instanceof Collection ? $row->all() : $row;

            $instance = $modele::updateOrCreate(
                $this->spec->cleUnique($ligne, $this->schoolId),
                $this->spec->transformer($ligne, $this->schoolId),
            );

            $instance->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;
        }
    }

    public function rules(): array
    {
        return $this->spec->regles();
    }
}
