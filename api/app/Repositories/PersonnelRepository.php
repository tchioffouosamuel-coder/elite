<?php

namespace App\Repositories;

use App\Models\Personnel;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonnelRepository extends BaseRepository
{
    public function __construct(Personnel $model)
    {
        parent::__construct($model);
    }

    public function paginateForSchool(int $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->forSchool($schoolId)
            ->with(['departement', 'user'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($filters['departement_id'] ?? null, fn ($query, $id) => $query->where('departement_id', $id))
            ->when($filters['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom')
            ->paginate($perPage);
    }
}
