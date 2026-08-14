<?php

namespace App\Repositories;

use App\Models\Eleve;
use Illuminate\Pagination\LengthAwarePaginator;

class EleveRepository extends BaseRepository
{
    public function __construct(Eleve $model)
    {
        parent::__construct($model);
    }

    public function paginateForSchool(int $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->forSchool($schoolId)
            ->with(['classe.niveau', 'tuteurs'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($filters['classe_id'] ?? null, fn ($query, $id) => $query->where('classe_id', $id))
            ->when($filters['sexe'] ?? null, fn ($query, $sexe) => $query->where('sexe', $sexe))
            ->when($filters['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom_complet')
            ->paginate($perPage);
    }
}
