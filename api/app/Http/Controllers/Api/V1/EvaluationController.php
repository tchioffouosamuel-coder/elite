<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ClasseMatiere;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Préparation des évaluations d'une affectation classe↔matière : questions,
 * barème, et éventuellement rattachement à une leçon du programme.
 */
class EvaluationController extends Controller
{
    public function __construct(private readonly EvaluationService $service) {}

    public function index(int $classeMatiereId): JsonResponse
    {
        return ApiResponse::success($this->service->liste($this->affectation($classeMatiereId)));
    }

    public function store(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);
        [$donnees, $questions] = $this->valider($request, $classeMatiere);

        $evaluation = $this->service->creer($classeMatiere, $donnees, $questions, $request->user()?->personnel?->id);

        return ApiResponse::created($evaluation, 'Évaluation créée.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $evaluation = $this->evaluation($id);
        [$donnees, $questions] = $this->valider($request, $evaluation->classeMatiere);

        return ApiResponse::success($this->service->modifier($evaluation, $donnees, $questions), 'Évaluation mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->evaluation($id)->delete();

        return ApiResponse::success(message: 'Évaluation supprimée.');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function valider(Request $request, ClasseMatiere $classeMatiere): array
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Evaluation::TYPES)],
            'date_prevue' => ['nullable', 'date'],
            'bareme' => ['required', 'integer', 'min:1', 'max:100'],
            'competences' => ['nullable', 'string', 'max:1000'],
            'progression_item_id' => [
                'nullable',
                Rule::exists('progression_items', 'id')->where('classe_matiere_id', $classeMatiere->id),
            ],
            'questions' => ['present', 'array'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.enonce' => ['required', 'string', 'max:1000'],
            'questions.*.bareme_question' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            [
                'titre' => $data['titre'],
                'type' => $data['type'],
                'date_prevue' => $data['date_prevue'] ?? null,
                'bareme' => $data['bareme'],
                'competences' => $data['competences'] ?? null,
                'progression_item_id' => $data['progression_item_id'] ?? null,
            ],
            $data['questions'],
        ];
    }

    private function affectation(int $id): ClasseMatiere
    {
        return ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($id);
    }

    private function evaluation(int $id): Evaluation
    {
        return Evaluation::forSchool(Tenant::schoolIds())->with('classeMatiere.classe')->findOrFail($id);
    }
}
