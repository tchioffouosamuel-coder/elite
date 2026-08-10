<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDepartementRequest;
use App\Http\Resources\Api\V1\DepartementResource;
use App\Models\Departement;
use Illuminate\Http\JsonResponse;

class DepartementController extends Controller
{
    public function index(): JsonResponse
    {
        $departements = Departement::forSchool(app('tenant.school_id'))->orderBy('nom')->get();

        return ApiResponse::success(DepartementResource::collection($departements));
    }

    public function store(StoreDepartementRequest $request): JsonResponse
    {
        $departement = Departement::create([...$request->validated(), 'school_id' => app('tenant.school_id')]);

        return ApiResponse::created(new DepartementResource($departement), 'Département créé.');
    }

    public function update(StoreDepartementRequest $request, int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);
        $departement->update($request->validated());

        return ApiResponse::success(new DepartementResource($departement), 'Département mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);
        $departement->delete();

        return ApiResponse::success(message: 'Département supprimé.');
    }
}
