<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNiveauScolaireRequest;
use App\Http\Resources\Api\V1\NiveauScolaireResource;
use App\Models\NiveauScolaire;
use Illuminate\Http\JsonResponse;

/**
 * Niveaux d'enseignement du primaire et de la maternelle (SIL, CP, CE1…),
 * chacun piloté par un animateur de niveau — l'équivalent du département et
 * de son chef au secondaire.
 */
class NiveauScolaireController extends Controller
{
    public function index(): JsonResponse
    {
        $niveaux = NiveauScolaire::forSchool(app('tenant.school_id'))
            ->with('animateur')
            ->withCount('classes')
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success(NiveauScolaireResource::collection($niveaux));
    }

    public function store(StoreNiveauScolaireRequest $request): JsonResponse
    {
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
}
