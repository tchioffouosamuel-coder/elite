<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionAnneeScolaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVisiteAutoriteRequest;
use App\Http\Requests\Api\V1\UpdateVisiteAutoriteRequest;
use App\Models\VisiteAutorite;
use App\Services\VisiteAutoriteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Visites d'autorités administratives et pédagogiques — tableau 22 du rapport de rentrée MINEDUB. */
class VisiteAutoriteController extends Controller
{
    use ResolutionAnneeScolaire;

    public function __construct(private readonly VisiteAutoriteService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->list($schoolId, $annee));
    }

    public function store(StoreVisiteAutoriteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->create($schoolId, $data), 'Visite enregistrée.');
    }

    public function update(UpdateVisiteAutoriteRequest $request, int $id): JsonResponse
    {
        $visite = VisiteAutorite::forSchool(Tenant::schoolIds())->findOrFail($id);
        $visite = $this->service->update($visite, $request->validated());

        return ApiResponse::success($visite, 'Visite mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $visite = VisiteAutorite::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->delete($visite);

        return ApiResponse::success(null, 'Visite supprimée.');
    }
}
