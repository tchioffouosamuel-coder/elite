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
            ->with(['departement', 'user', 'fonctionReference'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%")
                        ->orWhereHas('fonctionReference', fn ($fonction) => $fonction
                            ->where('label_fr', 'like', "%{$search}%")
                            ->orWhere('label_en', 'like', "%{$search}%"));
                });
            })
            ->when($filters['departement_id'] ?? null, fn ($query, $id) => $query->where('departement_id', $id))
            ->when($filters['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom_complet')
            ->paginate($perPage);
    }
}
