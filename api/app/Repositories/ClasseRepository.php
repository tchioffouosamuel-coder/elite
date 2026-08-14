<?php

namespace App\Repositories;

use App\Models\Classe;
use Illuminate\Database\Eloquent\Collection;

class ClasseRepository extends BaseRepository
{
    public function __construct(Classe $model)
    {
        parent::__construct($model);
    }

    public function forSchoolAndAnnee(int $schoolId, ?int $anneeScolaireId, array $filters = []): Collection
    {
        return $this->query()
            ->forSchool($schoolId)
            ->when($anneeScolaireId, fn ($query, $id) => $query->where('annee_scolaire_id', $id))
            ->when($filters['niveau_id'] ?? null, fn ($query, $id) => $query->where('niveau_id', $id))
            ->withCount('eleves')
            ->with(['niveau', 'niveauScolaire', 'sousSysteme', 'professeurPrincipal', 'titulaire'])
            ->orderBy('nom')
            ->get();
    }
}
