<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SuiviSaisiesService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Point 15 de la punch-list : tableau de bord admin/HM/principal — notes
 * manquantes et leçons en retard, pour la séquence active de l'école.
 * Réservé à la direction (cf. permission `pedagogie.suivi_saisies`, jamais
 * portée par l'enseignant ordinaire).
 */
class SuiviSaisiesController extends Controller
{
    public function __construct(private readonly SuiviSaisiesService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_if(Tenant::isAggregate(), 422, 'Veuillez sélectionner un établissement pour consulter ce suivi.');

        $schoolId = Tenant::schoolId();

        $data = $request->validate([
            'classe_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'matiere_id' => ['nullable', 'integer', Rule::exists('matieres', 'id')->where('school_id', $schoolId)],
        ]);

        $sequence = $this->service->sequenceActive($schoolId);

        return ApiResponse::success([
            'sequence' => $sequence ? [
                'id' => $sequence->id,
                'libelle' => $sequence->libelle,
                'trimestre' => $sequence->trimestre?->libelle,
            ] : null,
            'lignes' => $this->service->pourSequenceActive(
                $schoolId,
                $data['classe_id'] ?? null,
                $data['matiere_id'] ?? null,
            ),
        ]);
    }
}
