<?php

namespace App\Services;

use App\Models\AssuranceScolaire;
use Illuminate\Support\Collection;

class AssuranceScolaireService
{
    /** @param int|array<int> $schoolId */
    public function list(int|array $schoolId, int $anneeScolaireId): Collection
    {
        return AssuranceScolaire::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('id')
            ->get();
    }

    public function create(int $schoolId, array $attributes): AssuranceScolaire
    {
        return AssuranceScolaire::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(AssuranceScolaire $assurance, array $attributes): AssuranceScolaire
    {
        $assurance->update($attributes);

        return $assurance;
    }

    public function delete(AssuranceScolaire $assurance): void
    {
        $assurance->delete();
    }
}
