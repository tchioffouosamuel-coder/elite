<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveNotesRequest;
use App\Models\ClasseMatiere;
use App\Models\Sequence;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $service)
    {
    }

    public function index(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(app('tenant.school_id'))->with('classe')->findOrFail($classeMatiereId);
        $sequenceId = $request->integer('sequence_id');

        if (! $sequenceId) {
            return ApiResponse::error('Le paramètre sequence_id est requis.', 422);
        }

        return ApiResponse::success($this->service->grille($classeMatiere, $sequenceId)->values());
    }

    public function bulkStore(BulkSaveNotesRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(app('tenant.school_id'))->with('classe')->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $count = $this->service->sauvegarderEnLot(
            $classeMatiere,
            $request->integer('sequence_id'),
            $request->input('notes'),
            $request->user(),
        );

        return ApiResponse::success(['saved' => $count], "{$count} note(s) enregistrée(s).");
    }

    public function import(Request $request, int $classeMatiereId): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $classeMatiere = ClasseMatiere::forSchool($schoolId)->with('classe')->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv'], 'sequence_id' => ['required', 'integer']]);

        $sequence = Sequence::whereHas(
            'trimestre.anneeScolaire',
            fn ($q) => $q->where('school_id', $schoolId)
        )->findOrFail($request->integer('sequence_id'));

        $result = $this->service->importFromExcel($schoolId, $classeMatiere, $sequence->id, $request->user(), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} note(s) importée(s).");
    }
}
