<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveProgressionRequest;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ChampPersonnalise;
use App\Services\ProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /** Champs personnalisés définis pour une matière (tableaux d'informations spécifiques). */
    public function champs(int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        return ApiResponse::success(
            ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type', 'ordre'])
        );
    }

    public function enregistrerChamps(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        $data = $request->validate([
            'champs' => ['present', 'array'],
            'champs.*.id' => ['nullable', 'integer'],
            'champs.*.libelle' => ['required', 'string', 'max:100'],
            'champs.*.type' => ['required', Rule::in(ChampPersonnalise::TYPES)],
        ]);

        $conserves = [];

        foreach (array_values($data['champs']) as $ordre => $champ) {
            $attributs = [
                'classe_matiere_id' => $classeMatiere->id,
                'libelle' => $champ['libelle'],
                'type' => $champ['type'],
                'ordre' => $ordre + 1,
            ];

            $item = isset($champ['id'])
                ? ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)->find($champ['id'])
                : null;

            $item = $item ? tap($item)->update($attributs) : ChampPersonnalise::create($attributs);
            $conserves[] = $item->id;
        }

        // Un champ retiré de l'éditeur disparaît ; les valeurs déjà saisies
        // dans les séances passées restent en base, simplement orphelines.
        ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
            ->whereNotIn('id', $conserves ?: [0])
            ->delete();

        return ApiResponse::success(
            ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type', 'ordre']),
            'Champs personnalisés enregistrés.'
        );
    }

    private function affectation(int $id): ClasseMatiere
    {
        return ClasseMatiere::forSchool(app('tenant.school_id'))
            ->with(['classe', 'matiere'])
            ->findOrFail($id);
    }
}
