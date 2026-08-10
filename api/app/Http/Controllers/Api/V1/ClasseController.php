<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClasseRequest;
use App\Http\Resources\Api\V1\ClasseResource;
use App\Services\ClasseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function __construct(private readonly ClasseService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $classes = $this->service->list(
            app('tenant.school_id'),
            $request->integer('annee_scolaire_id') ?: null,
            $request->only(['niveau_id']),
        );

        return ApiResponse::success(ClasseResource::collection($classes));
    }

    public function store(StoreClasseRequest $request): JsonResponse
    {
        $classe = $this->service->create(app('tenant.school_id'), $request->validated());

        return ApiResponse::created(new ClasseResource($classe), 'Classe créée.');
    }

    public function show(int $id): JsonResponse
    {
        $classe = $this->service->find(app('tenant.school_id'), $id);

        return ApiResponse::success(new ClasseResource($classe));
    }

    public function update(StoreClasseRequest $request, int $id): JsonResponse
    {
        $classe = $this->service->find(app('tenant.school_id'), $id);
        $classe = $this->service->update($classe, $request->validated());

        return ApiResponse::success(new ClasseResource($classe), 'Classe mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $classe = $this->service->find(app('tenant.school_id'), $id);
        $this->service->delete($classe);

        return ApiResponse::success(message: 'Classe supprimée.');
    }
}
