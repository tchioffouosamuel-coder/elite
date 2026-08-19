<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\User;
use App\Repositories\ClasseRepository;
use Illuminate\Database\Eloquent\Collection;

class ClasseService extends BaseService
{
    private const RESPONSABLES = [
        'niveau', 'niveauScolaire', 'sousSysteme', 'professeurPrincipal', 'titulaire',
        'surveillantGeneral', 'censeur', 'conseillerOrientation',
    ];

    public function __construct(private readonly ClasseRepository $repository) {}

    public function list(int|array $schoolId, ?int $anneeScolaireId, array $filters = []): Collection
    {
        return $this->repository->forSchoolAndAnnee($schoolId, $anneeScolaireId, $filters);
    }

    public function find(int|array $schoolId, int $id): Classe
    {
        return $this->repository->query()->forSchool($schoolId)->with([...self::RESPONSABLES, 'school:id,name,code,type'])->findOrFail($id);
    }

    /**
     * Classe tenue par l'utilisateur connecté, s'il en est titulaire (primaire
     * et maternelle uniquement — le secondaire n'a pas de titulaire unique).
     */
    public function maClasse(User $user, int|array $schoolId): ?Classe
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return null;
        }

        return $this->repository->query()->forSchool($schoolId)->with(self::RESPONSABLES)
            ->where('titulaire_id', $personnelId)->first();
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
