<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionTrimestre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRapportTrimestreTexteRequest;
use App\Services\RapportTrimestreTexteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blocs de texte libre du rapport de fin de trimestre MINEDUB :
 * introduction, observations (structure, élèves, personnel), difficultés
 * rencontrées, conclusion générale.
 */
class RapportTrimestreTexteController extends Controller
{
    use ResolutionTrimestre;

    private const RUBRIQUES = [
        'introduction', 'observations_structure', 'observations_eleves',
        'observations_personnel', 'difficultes_rencontrees', 'conclusion_generale',
    ];

    public function __construct(private readonly RapportTrimestreTexteService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = $this->resolveTrimestre($request, $schoolId);

        return ApiResponse::success($this->service->all($schoolId, $trimestre));
    }

    public function update(StoreRapportTrimestreTexteRequest $request, string $rubrique): JsonResponse
    {
        if (! in_array($rubrique, self::RUBRIQUES, true)) {
            abort(404);
        }

        $data = $request->validated();
        $schoolId = app('tenant.school_id');

        $texte = $this->service->definir($schoolId, $data['trimestre_id'], $rubrique, $data['contenu'] ?? null);

        return ApiResponse::success($texte, 'Enregistré.');
    }
}
