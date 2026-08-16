<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Services\BilanFinancierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * États financiers : compte de résultat, trésorerie, balance et tableau de
 * bord. Tous se reconstruisent depuis le journal comptable, ce qui garantit
 * qu'ils racontent la même histoire.
 */
class RapportFinancierController extends Controller
{
    public function __construct(private readonly BilanFinancierService $service) {}

    public function resultat(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->resultat(app('tenant.school_id'), $this->filtres($request)),
        );
    }

    public function tresorerie(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->tresorerie(app('tenant.school_id'), $this->filtres($request)),
        );
    }

    public function balance(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->balance(app('tenant.school_id'), $this->filtres($request)),
        );
    }

    public function tableauDeBord(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');

        $annee = $request->integer('annee_scolaire_id')
            ? AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'))
            : AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail();

        return ApiResponse::success(
            $this->service->tableauDeBord($schoolId, $annee->id, $this->filtres($request)),
        );
    }

    /** @return array{du: ?string, au: ?string, annee_scolaire_id: ?int} */
    private function filtres(Request $request): array
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'annee_scolaire_id' => ['nullable', 'integer'],
        ]);

        return [
            'du' => $request->string('du')->toString() ?: null,
            'au' => $request->string('au')->toString() ?: null,
            'annee_scolaire_id' => $request->integer('annee_scolaire_id') ?: null,
        ];
    }
}
