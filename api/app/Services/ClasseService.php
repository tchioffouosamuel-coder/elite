<?php

namespace App\Services;

use App\Models\Classe;
use App\Repositories\ClasseRepository;
use Illuminate\Database\Eloquent\Collection;

class ClasseService extends BaseService
{
    private const RESPONSABLES = [
        'niveau', 'niveauScolaire', 'professeurPrincipal', 'titulaire',
        'surveillantGeneral', 'censeur', 'conseillerOrientation',
    ];

    public function __construct(private readonly ClasseRepository $repository) {}

    public function list(int $schoolId, ?int $anneeScolaireId, array $filters = []): Collection
    {
        return $this->repository->forSchoolAndAnnee($schoolId, $anneeScolaireId, $filters);
    }

    public function find(int $schoolId, int $id): Classe
    {
        return $this->repository->query()->forSchool($schoolId)->with(self::RESPONSABLES)->findOrFail($id);
    }

    public function create(int $schoolId, array $attributes): Classe
    {
        return $this->repository->create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(Classe $classe, array $attributes): Classe
    {
        return $this->repository->update($classe, $attributes);
    }

    public function delete(Classe $classe): bool
    {
        return $this->repository->delete($classe);
    }
}
