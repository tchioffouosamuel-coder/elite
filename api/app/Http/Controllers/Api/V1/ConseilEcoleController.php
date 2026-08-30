<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreConseilEcoleRequest;
use App\Models\AnneeScolaire;
use App\Services\GouvernanceEcoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fonctionnement du conseil d'école — tableau 29 du rapport de rentrée MINEDUB. */
class ConseilEcoleController extends Controller
{
    public function __construct(private readonly GouvernanceEcoleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->conseilEcole($schoolId, $annee));
    }

    public function update(StoreConseilEcoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = app('tenant.school_id');
        $anneeScolaireId = $data['annee_scolaire_id'];
        unset($data['annee_scolaire_id']);

        return ApiResponse::success(
            $this->service->definirConseilEcole($schoolId, $anneeScolaireId, $data),
            'Conseil d\'école mis à jour.',
        );
    }

    private function resolveAnnee(Request $request, int $schoolId): int
    {
        if ($request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'))->id;
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail()->id;
    }
}
