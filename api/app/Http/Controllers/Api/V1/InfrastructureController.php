<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEquipementMobilierRequest;
use App\Http\Requests\Api\V1\StoreInfrastructureRequest;
use App\Http\Requests\Api\V1\UpdateEquipementMobilierRequest;
use App\Http\Requests\Api\V1\UpdateInfrastructureRequest;
use App\Models\EquipementMobilier;
use App\Models\Infrastructure;
use App\Services\InfrastructureService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Bâti, mobilier et équipements de l'école — infrastructures.view/manage
 * couvre les tableaux 18 à 20 du rapport de rentrée MINEDUB (salles de
 * classe, bloc administratif, autres installations, mobilier et besoins).
 */
class InfrastructureController extends Controller
{
    public function __construct(private readonly InfrastructureService $service) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->service->listInfrastructures(Tenant::schoolIds()));
    }

    public function store(StoreInfrastructureRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->createInfrastructure($schoolId, $data), 'Infrastructure ajoutée.');
    }

    public function update(UpdateInfrastructureRequest $request, int $id): JsonResponse
    {
        $infrastructure = Infrastructure::forSchool(Tenant::schoolIds())->findOrFail($id);
        $infrastructure = $this->service->updateInfrastructure($infrastructure, $request->validated());

        return ApiResponse::success($infrastructure, 'Infrastructure mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $infrastructure = Infrastructure::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->deleteInfrastructure($infrastructure);

        return ApiResponse::success(null, 'Infrastructure supprimée.');
    }

    public function equipements(): JsonResponse
    {
        return ApiResponse::success($this->service->listEquipements(Tenant::schoolIds()));
    }

    public function storeEquipement(StoreEquipementMobilierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->createEquipement($schoolId, $data), 'Équipement ajouté.');
    }

    public function updateEquipement(UpdateEquipementMobilierRequest $request, int $id): JsonResponse
    {
        $equipement = EquipementMobilier::forSchool(Tenant::schoolIds())->findOrFail($id);
        $equipement = $this->service->updateEquipement($equipement, $request->validated());

        return ApiResponse::success($equipement, 'Équipement mis à jour.');
    }

    public function destroyEquipement(int $id): JsonResponse
    {
        $equipement = EquipementMobilier::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->deleteEquipement($equipement);

        return ApiResponse::success(null, 'Équipement supprimé.');
    }

    public function rapport(): JsonResponse
    {
        return ApiResponse::success($this->service->rapport(Tenant::schoolIds()));
    }
}
