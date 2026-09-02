<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SuiviActiviteService;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suivi transverse prévu/réalisé du personnel — le pendant admin de
 * `MaJourneeController`, qui ne renseigne l'enseignant que sur lui-même.
 */
class SuiviActiviteController extends Controller
{
    public function __construct(private readonly SuiviActiviteService $service) {}

    public function parPersonnel(Request $request): JsonResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement pour consulter ce suivi.");

        $data = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'granularite' => ['nullable', 'in:jour,semaine,mois,annee'],
            'personnel_id' => ['nullable', 'integer', 'exists:personnels,id'],
        ]);

        $debut = isset($data['date_debut']) ? CarbonImmutable::parse($data['date_debut']) : CarbonImmutable::now()->startOfMonth();
        $fin = isset($data['date_fin']) ? CarbonImmutable::parse($data['date_fin']) : CarbonImmutable::now()->endOfMonth();

        return ApiResponse::success(
            $this->service->parPersonnel(
                Tenant::schoolId(),
                $debut->startOfDay(),
                $fin->endOfDay(),
                $data['granularite'] ?? 'jour',
                $data['personnel_id'] ?? null,
            )
        );
    }
}
