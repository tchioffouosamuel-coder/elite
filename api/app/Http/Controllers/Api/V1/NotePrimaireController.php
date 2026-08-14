<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveNotesPrimaireRequest;
use App\Models\ClasseMatiere;
use App\Models\Trimestre;
use App\Services\NotePrimaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saisie des notes au primaire et en maternelle : la grille couvre tout le
 * trimestre d'un coup — un bloc de colonnes par volet d'évaluation, une
 * colonne par séquence — au lieu d'une note par séquence comme au secondaire.
 */
class NotePrimaireController extends Controller
{
    public function __construct(private readonly NotePrimaireService $service) {}

    public function index(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(app('tenant.school_id'))
            ->with(['classe', 'matiere'])
            ->findOrFail($classeMatiereId);

        $trimestre = $this->resoudreTrimestre($request);

        if (! $trimestre) {
            return ApiResponse::error('Aucun trimestre actif pour cet établissement.', 422);
        }

        return ApiResponse::success($this->service->grille($classeMatiere, $trimestre));
    }

    public function bulkStore(BulkSaveNotesPrimaireRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(app('tenant.school_id'))
            ->with(['classe', 'matiere'])
            ->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas le titulaire de cette classe.");
        }

        // Une note ne peut dépasser la part du barème allouée à son volet —
        // répartition propre à la matière, pas le barème divisé à parts égales.
        $repartition = $classeMatiere->matiere->repartitionVolets();

        foreach ($request->input('notes') as $index => $row) {
            $maxVolet = $repartition[$row['composante']] ?? 0;

            if (isset($row['valeur']) && $row['valeur'] !== null && (float) $row['valeur'] > $maxVolet) {
                return ApiResponse::validationError(
                    ["notes.{$index}.valeur" => ['Ce volet est noté sur '.round($maxVolet, 2).' au maximum.']],
                );
            }
        }

        $count = $this->service->sauvegarderEnLot($classeMatiere, $request->input('notes'), $request->user());

        return ApiResponse::success(['saved' => $count], "{$count} note(s) enregistrée(s).");
    }

    private function resoudreTrimestre(Request $request): ?Trimestre
    {
        $schoolId = app('tenant.school_id');

        $query = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId));

        return $request->integer('trimestre_id')
            ? (clone $query)->find($request->integer('trimestre_id'))
            : (clone $query)->where('is_active', true)->first();
    }
}
