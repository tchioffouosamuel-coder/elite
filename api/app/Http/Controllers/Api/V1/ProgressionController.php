<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveProgressionRequest;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Services\ProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Programme d'enseignement annuel — modules, chapitres et leçons — et taux
 * d'avancement qui s'en déduit. Commun aux trois cycles : seule la façon de
 * découper les matières change, pas la nature du programme.
 */
class ProgressionController extends Controller
{
    public function __construct(private readonly ProgressionService $service) {}

    /** Programme d'une affectation classe↔matière. */
    public function show(int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        return ApiResponse::success([
            'classe' => ['id' => $classeMatiere->classe->id, 'nom' => $classeMatiere->classe->nom],
            'matiere' => ['id' => $classeMatiere->matiere->id, 'nom' => $classeMatiere->matiere->nom],
            'items' => $this->service->arbre($classeMatiere),
            ...$this->service->tauxAffectation($classeMatiere),
        ]);
    }

    public function save(SaveProgressionRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);
        $compte = $this->service->remplacerArbre($classeMatiere, $request->input('items', []));

        return ApiResponse::success(
            ['items' => $this->service->arbre($classeMatiere), ...$this->service->tauxAffectation($classeMatiere)],
            "{$compte} élément(s) enregistré(s)."
        );
    }

    /** Avancement de chaque matière d'une classe. */
    public function classe(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->with('titulaire')->findOrFail($classeId);

        return ApiResponse::success($this->service->tauxClasse($classe));
    }

    /** Avancement de l'établissement, classe par classe. */
    public function etablissement(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->tauxEtablissement(
            app('tenant.school_id'),
            $request->integer('annee_scolaire_id') ?: null,
        ));
    }

    private function affectation(int $id): ClasseMatiere
    {
        return ClasseMatiere::forSchool(app('tenant.school_id'))
            ->with(['classe', 'matiere'])
            ->findOrFail($id);
    }
}
