<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\User;
use App\Repositories\ClasseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ClasseService extends BaseService
{
    private const RESPONSABLES = [
        'niveau', 'niveauScolaire', 'sousSysteme', 'professeurPrincipal', 'titulaire',
        'surveillantGeneral', 'censeur', 'conseillerOrientation',
    ];

    public function __construct(private readonly ClasseRepository $repository) {}

    /**
     * Classes visibles par le compte : celles de l'établissement, bornées à
     * son périmètre quand il en a un — un enseignant, un censeur ou un
     * surveillant général ne voit que ce qu'il enseigne et ce qui lui a été
     * confié, là où la direction voit tout.
     */
    public function list(?User $user, int|array $schoolId, ?int $anneeScolaireId, array $filters = []): Collection
    {
        return $this->repository->forSchoolAndAnnee($user, $schoolId, $anneeScolaireId, $filters);
    }

    public function find(int|array $schoolId, int $id): Classe
    {
        return $this->repository->query()->forSchool($schoolId)->with([...self::RESPONSABLES, 'school:id,name,code,type'])->findOrFail($id);
    }

    /**
     * Classes confiées au compte au titre d'une attribution nominative,
     * regroupées par attribution — la matière de l'écran « Mes attributions »
     * et des entrées de menu qui en découlent.
     *
     * @return SupportCollection<int, array{code: string, libelle: string, portee: string, departements: list<int>, classes: Collection<int, Classe>}>
     */
    public function mesAttributions(User $user, int|array $schoolId): SupportCollection
    {
        $resume = $user->perimetre()->resume();

        $classes = $this->repository->query()
            ->forSchool($schoolId)
            ->whereIn('id', collect($resume)->pluck('classes')->flatten()->unique()->all())
            ->with([...self::RESPONSABLES, 'school:id,name,code,type'])
            ->withCount('eleves')
            ->orderBy('nom')
            ->get()
            ->keyBy('id');

        return collect($resume)->map(fn (array $attribution) => [
            ...$attribution,
            'classes' => collect($attribution['classes'])
                ->map(fn (int $id) => $classes->get($id))
                ->filter()
                ->values(),
        ])->values();
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
