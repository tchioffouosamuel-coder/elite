<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMatiereRequest;
use App\Http\Resources\Api\V1\MatiereResource;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Matiere::forSchool(app('tenant.school_id'))->with('departement')->orderBy('nom')->get();

        return ApiResponse::success(MatiereResource::collection($matieres));
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $matiere = Matiere::create([...$request->validated(), 'school_id' => app('tenant.school_id')])->refresh();

        return ApiResponse::created(new MatiereResource($matiere), 'Matière créée.');
    }

    public function update(StoreMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $matiere->update($request->validated());

        return ApiResponse::success(new MatiereResource($matiere), 'Matière mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $matiere->delete();

        return ApiResponse::success(message: 'Matière supprimée.');
    }
}
