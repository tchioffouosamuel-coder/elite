<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionAnneeScolaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVenteDenreeRequest;
use App\Http\Requests\Api\V1\UpdateVenteDenreeRequest;
use App\Models\VenteDenree;
use App\Services\VenteDenreeService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vente de denrées alimentaires à l'école — tableau 28 du rapport de rentrée MINEDUB. */
class VenteDenreeController extends Controller
{
    use ResolutionAnneeScolaire;

    public function __construct(private readonly VenteDenreeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->list($schoolId, $annee));
    }

    public function store(StoreVenteDenreeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->create($schoolId, $data), 'Vente enregistrée.');
    }

    public function update(UpdateVenteDenreeRequest $request, int $id): JsonResponse
    {
        $vente = VenteDenree::forSchool(Tenant::schoolIds())->findOrFail($id);
        $vente = $this->service->update($vente, $request->validated());

        return ApiResponse::success($vente, 'Vente mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $vente = VenteDenree::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->delete($vente);

        return ApiResponse::success(null, 'Vente supprimée.');
    }
}
