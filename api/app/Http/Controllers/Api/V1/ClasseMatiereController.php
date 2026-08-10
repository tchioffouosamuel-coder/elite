<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClasseMatiereRequest;
use App\Http\Requests\Api\V1\UpdateClasseMatiereRequest;
use App\Http\Resources\Api\V1\ClasseMatiereResource;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use Illuminate\Http\JsonResponse;

class ClasseMatiereController extends Controller
{
    public function index(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        $affectations = $classe->classeMatieres()->with(['matiere', 'enseignant'])->orderBy('groupe')->get();

        return ApiResponse::success(ClasseMatiereResource::collection($affectations));
    }

    public function store(StoreClasseMatiereRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        $affectation = $classe->classeMatieres()
            ->create([...$request->validated(), 'groupe' => $request->input('groupe', 1)])
            ->refresh();

        return ApiResponse::created(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Matière affectée à la classe.');
    }

    public function update(UpdateClasseMatiereRequest $request, int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $affectation->update($request->validated());

        return ApiResponse::success(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Affectation mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $affectation->delete();

        return ApiResponse::success(message: 'Matière retirée de la classe.');
    }
}
