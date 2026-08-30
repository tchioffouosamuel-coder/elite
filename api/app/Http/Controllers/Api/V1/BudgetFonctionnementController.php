<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBudgetFonctionnementRequest;
use App\Models\AnneeScolaire;
use App\Services\BudgetFonctionnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Budget de fonctionnement par rubrique (tableau 21 du rapport de rentrée
 * MINEDUB : Primes de rendement, Projet d'école, FENASSCO, Fonctionnement,
 * Évaluation) — le perçu se saisit ici, le dépensé se lit sur les dépenses
 * taguées `rubrique_budget_fonctionnement`.
 */
class BudgetFonctionnementController extends Controller
{
    private const RUBRIQUES = ['primes_rendement', 'projet_ecole', 'fenassco', 'fonctionnement', 'evaluation'];

    public function __construct(private readonly BudgetFonctionnementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->rapport($schoolId, $annee));
    }

    public function update(StoreBudgetFonctionnementRequest $request, string $rubrique): JsonResponse
    {
        if (! in_array($rubrique, self::RUBRIQUES, true)) {
            abort(404);
        }

        $data = $request->validated();
        $schoolId = app('tenant.school_id');

        $budget = $this->service->definirMontantPercu(
            $schoolId,
            $data['annee_scolaire_id'],
            $rubrique,
            $data['montant_percu'],
            $data['observations'] ?? null,
        );

        return ApiResponse::success($budget, 'Montant perçu mis à jour.');
    }

    private function resolveAnnee(Request $request, int $schoolId): int
    {
        if ($request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'))->id;
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail()->id;
    }
}
