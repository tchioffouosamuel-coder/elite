<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\RestreintParTypeEcole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNiveauScolaireRequest;
use App\Http\Resources\Api\V1\NiveauScolaireResource;
use App\Models\NiveauScolaire;
use App\Models\School;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Niveaux d'enseignement du primaire (SIL, CP, CE1…), chacun piloté par un
 * animateur de niveau — l'équivalent du département et de son chef au
 * secondaire.
 *
 * Le secondaire et la maternelle ne s'organisent pas ainsi : le premier suit
 * ses départements, la seconde ses seules sections. Le module leur est donc
 * fermé, et pas seulement masqué dans le menu.
 */
class NiveauScolaireController extends Controller
{
    use RestreintParTypeEcole;

    public function index(): JsonResponse
    {
        if ($refus = $this->refuserSaufPour('primaire')) {
            return $refus;
        }

        $niveaux = NiveauScolaire::forSchool($this->schoolIdsPour('primaire'))
            ->with(['animateur', 'school:id,name,code,type'])
            ->withCount('classes')
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success(NiveauScolaireResource::collection($niveaux));
    }

    public function store(StoreNiveauScolaireRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        if (School::find($schoolId)?->type !== 'primaire') {
            return ApiResponse::error($this->messageRefus(), 422);
        }

        $niveau = NiveauScolaire::create([...$data, 'school_id' => $schoolId]);

        return ApiResponse::created(new NiveauScolaireResource($niveau->load(['animateur', 'school:id,name,code,type'])), 'Niveau créé.');
    }

    public function update(StoreNiveauScolaireRequest $request, int $id): JsonResponse
    {
        $niveau = NiveauScolaire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);
        $niveau->update($data);

        return ApiResponse::success(new NiveauScolaireResource($niveau->load(['animateur', 'school:id,name,code,type'])), 'Niveau mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $niveau = NiveauScolaire::forSchool(Tenant::schoolIds())->withCount('classes')->findOrFail($id);

        if ($niveau->classes_count > 0) {
            return ApiResponse::error('Ce niveau est encore rattaché à des classes.', 422);
        }

        $niveau->delete();

        return ApiResponse::success(message: 'Niveau supprimé.');
    }

    protected function messageRefus(): string
    {
        return "Cet établissement ne s'organise pas en niveaux d'enseignement.";
    }
}
