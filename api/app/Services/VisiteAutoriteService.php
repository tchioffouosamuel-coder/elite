<?php

namespace App\Services;

use App\Models\VisiteAutorite;
use Illuminate\Support\Collection;

class VisiteAutoriteService
{
    /** @param int|array<int> $schoolId */
    public function list(int|array $schoolId, int $anneeScolaireId): Collection
    {
        return VisiteAutorite::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderByDesc('date_visite')
            ->get();
    }

    public function create(int $schoolId, array $attributes): VisiteAutorite
    {
        return VisiteAutorite::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(VisiteAutorite $visite, array $attributes): VisiteAutorite
    {
        $visite->update($attributes);

        return $visite;
    }

    public function delete(VisiteAutorite $visite): void
    {
        $visite->delete();
    }
}
