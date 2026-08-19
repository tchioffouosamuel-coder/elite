<?php

namespace App\Repositories;

use App\Models\Personnel;
use App\Support\Attributions;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonnelRepository extends BaseRepository
{
    public function __construct(Personnel $model)
    {
        parent::__construct($model);
    }

    /** @param int|array<int> $schoolId */
    public function paginateForSchool(int|array $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->forSchool($schoolId)
            ->with(['departement', 'user', 'fonctionReference', 'school:id,name,code,type'])
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
            ->when($filters['fonction_id'] ?? null, fn ($query, $id) => $query->where('fonction_id', $id))
            ->when($filters['fonction_label'] ?? null, function ($query, $label) {
                $query->whereHas('fonctionReference', fn ($fonction) => $fonction->whereRaw('LOWER(label_fr) = ?', [mb_strtolower($label)]));
            })
            // Candidats plausibles à une responsabilité nominative : un
            // enseignant peut être désigné surveillant général d'une classe,
            // un économe non.
            ->when(
                Attributions::existe((string) ($filters['attribution'] ?? '')) ? $filters['attribution'] : null,
                fn ($query, $code) => $query->whereIn('fonction_id', Attributions::fonctionsEligibles($code, $schoolId)),
            )
            ->when($filters['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom_complet')
            ->paginate($perPage);
    }
}
