<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNiveauScolaireRequest;
use App\Http\Resources\Api\V1\NiveauScolaireResource;
use App\Models\NiveauScolaire;
use App\Models\School;
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
    public function index(): JsonResponse
    {
        if ($refus = $this->refuserSiHorsPrimaire()) {
            return $refus;
        }

        $niveaux = NiveauScolaire::forSchool(app('tenant.school_id'))
            ->with('animateur')
            ->withCount('classes')
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success(NiveauScolaireResource::collection($niveaux));
    }

    public function store(StoreNiveauScolaireRequest $request): JsonResponse
    {
        if ($refus = $this->refuserSiHorsPrimaire()) {
            return $refus;
        }

        $niveau = NiveauScolaire::create([
            ...$request->validated(),
            'school_id' => app('tenant.school_id'),
        ]);

        return ApiResponse::created(new NiveauScolaireResource($niveau->load('animateur')), 'Niveau créé.');
    }

    public function update(StoreNiveauScolaireRequest $request, int $id): JsonResponse
    {
        $niveau = NiveauScolaire::forSchool(app('tenant.school_id'))->findOrFail($id);
        $niveau->update($request->validated());

        return ApiResponse::success(new NiveauScolaireResource($niveau->load('animateur')), 'Niveau mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $niveau = NiveauScolaire::forSchool(app('tenant.school_id'))->withCount('classes')->findOrFail($id);

        if ($niveau->classes_count > 0) {
            return ApiResponse::error('Ce niveau est encore rattaché à des classes.', 422);
        }

        $niveau->delete();

        return ApiResponse::success(message: 'Niveau supprimé.');
    }

    private function refuserSiHorsPrimaire(): ?JsonResponse
    {
        return School::find(app('tenant.school_id'))?->type === 'primaire'
            ? null
            : ApiResponse::error("Cet établissement ne s'organise pas en niveaux d'enseignement.", 422);
    }
}
