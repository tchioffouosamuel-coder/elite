<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreApeeRequest;
use App\Models\AnneeScolaire;
use App\Services\GouvernanceEcoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Situation de l'APEE — tableau 30 du rapport de rentrée MINEDUB. */
class ApeeController extends Controller
{
    public function __construct(private readonly GouvernanceEcoleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->apee($schoolId, $annee));
    }

    public function update(StoreApeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = app('tenant.school_id');
        $anneeScolaireId = $data['annee_scolaire_id'];
        unset($data['annee_scolaire_id']);

        return ApiResponse::success(
            $this->service->definirApee($schoolId, $anneeScolaireId, $data),
            'APEE mise à jour.',
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
