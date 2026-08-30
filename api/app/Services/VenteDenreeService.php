<?php

namespace App\Services;

use App\Models\VenteDenree;
use Illuminate\Support\Collection;

class VenteDenreeService
{
    /** @param int|array<int> $schoolId */
    public function list(int|array $schoolId, int $anneeScolaireId): Collection
    {
        return VenteDenree::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('id')
            ->get();
    }

    public function create(int $schoolId, array $attributes): VenteDenree
    {
        return VenteDenree::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(VenteDenree $vente, array $attributes): VenteDenree
    {
        $vente->update($attributes);

        return $vente;
    }

    public function delete(VenteDenree $vente): void
    {
        $vente->delete();
    }
}
