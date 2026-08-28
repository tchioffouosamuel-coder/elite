<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\PilotageService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
        private readonly PilotageService $pilotage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->stats(Tenant::schoolIds(), $request->user()));
    }

    /**
     * Pilotage en temps réel (cours en cours, classes sans enseignant,
     * couverture du programme) : bloc coûteux chargé à la demande, séparé de
     * `index()` pour ne pas alourdir l'ouverture du tableau de bord.
     */
    public function pilotage(): JsonResponse
    {
        return ApiResponse::success($this->pilotage->pilotage(Tenant::schoolIds()));
    }
}
