<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AnciensReinscritsExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EleveResource;
use App\Services\DashboardService;
use App\Services\PilotageService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** Liste détaillée des anciens élèves réinscrits pour l'année active. */
    public function anciensReinscrits(): JsonResponse
    {
        $eleves = $this->service->listeAnciensReinscrits(Tenant::schoolIds());
        $eleves = $eleves->sortBy(fn($eleve) => mb_strtolower($eleve->nom_complet))->values();
        $eleves->load(['school', 'classe.niveau']);

        return ApiResponse::success(EleveResource::collection($eleves));
    }

    public function exportAnciensReinscrits(): BinaryFileResponse
    {
        return Excel::download(
            new AnciensReinscritsExport(Tenant::schoolIds(), app(\App\Services\PreinscriptionService::class)),
            'anciens-reinscrits.xlsx',
        );
    }

    /**
     * Journal complet (paginé) derrière le « Voir plus » de la carte Activité
     * récente — celle-ci ne montre qu'un aperçu des 6 dernières lignes.
     */
    public function activiteRecente(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->service->activiteRecentePaginee(Tenant::schoolIds(), (int) $request->integer('per_page', 25)),
        );
    }
}
