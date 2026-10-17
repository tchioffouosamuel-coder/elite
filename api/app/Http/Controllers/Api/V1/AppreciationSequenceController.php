<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveAppreciationsSequenceRequest;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Services\AppreciationSequenceService;
use App\Services\NotePrimaireService;
use App\Services\NoteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appréciations de séquence (point 16 de la punch-list) : un commentaire
 * court par élève, saisi par l'enseignant de la matière (secondaire) ou de la
 * compétence (primaire/maternelle), sur la 1re ou la 2e séquence d'un
 * trimestre. Même périmètre que la saisie des notes ({@see NoteController},
 * {@see NotePrimaireController}), dont on réutilise ici `peutSaisir()` plutôt
 * que de le redéfinir.
 */
class AppreciationSequenceController extends Controller
{
    public function __construct(
        private readonly AppreciationSequenceService $service,
        private readonly NoteService $notes,
        private readonly NotePrimaireService $notesPrimaire,
    ) {}

    public function index(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);
        $sequenceId = $request->integer('sequence_id');

        if (! $sequenceId) {
            return ApiResponse::error('Le paramètre sequence_id est requis.', 422);
        }

        return ApiResponse::success($this->service->grille($classeMatiere, $sequenceId)->values());
    }

    public function bulkStore(BulkSaveAppreciationsSequenceRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);

        if (! $this->notes->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $count = $this->service->sauvegarderEnLot(
            $classeMatiere,
            $request->integer('sequence_id'),
            $request->input('appreciations'),
            $request->user(),
        );

        return ApiResponse::success(['saved' => $count], "{$count} appréciation(s) enregistrée(s).");
    }

    public function indexPrimaire(Request $request, int $classeCompetenceId): JsonResponse
    {
        $classeCompetence = ClasseCompetence::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeCompetenceId);
        $sequenceId = $request->integer('sequence_id');

        if (! $sequenceId) {
            return ApiResponse::error('Le paramètre sequence_id est requis.', 422);
        }

        return ApiResponse::success($this->service->grillePrimaire($classeCompetence, $sequenceId)->values());
    }

    public function bulkStorePrimaire(BulkSaveAppreciationsSequenceRequest $request, int $classeCompetenceId): JsonResponse
    {
        $classeCompetence = ClasseCompetence::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeCompetenceId);

        if (! $this->notesPrimaire->peutSaisir($request->user(), $classeCompetence)) {
            return ApiResponse::forbidden("Vous n'êtes pas le titulaire de cette classe.");
        }

        $count = $this->service->sauvegarderEnLotPrimaire(
            $classeCompetence,
            $request->integer('sequence_id'),
            $request->input('appreciations'),
            $request->user(),
        );

        return ApiResponse::success(['saved' => $count], "{$count} appréciation(s) enregistrée(s).");
    }
}
