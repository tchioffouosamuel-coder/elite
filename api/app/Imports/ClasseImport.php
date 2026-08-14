<?php

namespace App\Imports;

use App\Models\AnneeScolaire;
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
 *
 * `annee_scolaire_id` est obligatoire en base (contrainte `constrained()` sans
 * `nullable()`) : une école sans année scolaire voit ses lignes ignorées
 * plutôt que de faire planter l'import entier sur une erreur de base de données.
 */
class ClasseImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public int $importedCount = 0;

    private ?int $anneeScolaireId = null;

    public function __construct(private readonly int $schoolId)
    {
    }

    public function model(array $row): ?Classe
    {
        $anneeScolaireId = $this->anneeScolaireId();

        if (! $anneeScolaireId) {
            return null;
        }

        $this->importedCount++;

        return new Classe([
            'school_id' => $this->schoolId,
            'annee_scolaire_id' => $anneeScolaireId,
            'nom' => $row['nom'],
            'sigle' => $row['sigle'] ?? null,
            'capacite' => $row['capacite'] ?? null,
        ]);
    }

    private function anneeScolaireId(): ?int
    {
        if ($this->anneeScolaireId !== null) {
            return $this->anneeScolaireId;
        }

        $annee = AnneeScolaire::where('school_id', $this->schoolId)->where('is_active', true)->first()
            ?? AnneeScolaire::where('school_id', $this->schoolId)->first();

        return $this->anneeScolaireId = $annee?->id;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string'],
        ];
    }
}
