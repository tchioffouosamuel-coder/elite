<?php

namespace App\Services;

use App\Models\ActiviteRentree;
use Illuminate\Support\Collection;

class ActiviteRentreeService
{
    /** @param int|array<int> $schoolId */
    public function list(int|array $schoolId, int $anneeScolaireId, ?string $categorie = null): Collection
    {
        return ActiviteRentree::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->when($categorie, fn ($q) => $q->where('categorie', $categorie))
            ->orderBy('id')
            ->get();
    }

    public function create(int $schoolId, array $attributes): ActiviteRentree
    {
        return ActiviteRentree::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(ActiviteRentree $activite, array $attributes): ActiviteRentree
    {
        $activite->update($attributes);

        return $activite;
    }

    public function delete(ActiviteRentree $activite): void
    {
        $activite->delete();
    }
}
