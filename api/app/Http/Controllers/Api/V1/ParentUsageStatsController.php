<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ParentUsageStatsService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suivi d'adoption et d'usage du portail parent — adoption des comptes, activité, volumes déposés, délai de traitement. */
class ParentUsageStatsController extends Controller
{
    public function __construct(private readonly ParentUsageStatsService $service) {}

    public function index(Request $request): JsonResponse
    {
        $jours = $request->integer('jours') ?: 30;
        $jours = in_array($jours, [7, 30, 90], true) ? $jours : 30;

        return ApiResponse::success($this->service->resume(Tenant::schoolIds(), $jours));
    }

    /** Détail nominatif derrière une carte « Comptes parents »/« Comptes du personnel » cliquée. */
    public function comptes(Request $request): JsonResponse
    {
        $jours = $request->integer('jours') ?: 30;
        $jours = in_array($jours, [7, 30, 90], true) ? $jours : 30;

        $segment = $request->string('segment')->toString();
        if (! in_array($segment, ['parents', 'personnel'], true)) {
            return ApiResponse::error('Segment de comptes invalide.', 422);
        }

        $categorie = $request->string('categorie')->toString();
        $categoriesValides = ['total', 'ouverts_dans_periode', 'actifs', 'dormants', 'jamais_connectes', 'desactives'];
        if (! in_array($categorie, $categoriesValides, true)) {
            return ApiResponse::error('Catégorie de comptes invalide.', 422);
        }

        return ApiResponse::success($this->service->listeComptes(Tenant::schoolIds(), $segment, $categorie, $jours));
    }
}
