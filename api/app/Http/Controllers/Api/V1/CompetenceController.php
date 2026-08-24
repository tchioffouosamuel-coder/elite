<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompetenceRequest;
use App\Http\Resources\Api\V1\CompetenceResource;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use App\Services\CompetenceAttributionService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel des compétences évaluées et leur attribution aux classes.
 *
 * Une compétence porte le barème et les volets ; les matières en sont le
 * contenu. Attribuer une compétence à une classe y installe d'office ses
 * matières — l'utilisateur choisit un bloc, pas une liste.
 */
class CompetenceController extends Controller
{
    public function __construct(private readonly CompetenceAttributionService $attribution) {}

    public function index(): JsonResponse
    {
        $competences = Competence::forSchool(Tenant::schoolIds())
            ->with(['matieres:id,competence_id,nom,nom_en,abbreviation', 'school:id,name,code,type'])
            ->withCount(['matieres', 'classeCompetences'])
            ->orderBy('ordre')
            ->orderBy('label_fr')
            ->get();

        return ApiResponse::success(CompetenceResource::collection($competences));
    }

    public function store(StoreCompetenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $competence = Competence::create([...$data, 'school_id' => $schoolId])->refresh();
        $competence->load('school:id,name,code,type');

        return ApiResponse::created(new CompetenceResource($competence), 'Compétence créée.');
    }

    public function update(StoreCompetenceRequest $request, int $id): JsonResponse
    {
        $competence = Competence::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);

        $competence->update($data);
        $competence->load(['matieres', 'school:id,name,code,type']);

        return ApiResponse::success(new CompetenceResource($competence), 'Compétence mise à jour.');
    }

    /**
     * Supprime une compétence, et avec elle ses matières et les attributions
     * qui en découlent — la cascade est posée en base. L'opération est refusée
     * dès qu'une note existe : le bulletin d'un trimestre déjà rempli ne doit
     * pas perdre une de ses lignes.
     */
    public function destroy(int $id): JsonResponse
    {
        $competence = Competence::forSchool(Tenant::schoolIds())->findOrFail($id);

        $notes = $this->notesCount($competence);

        if ($notes > 0) {
            return ApiResponse::error(
                "Cette compétence porte déjà {$notes} note(s) : rendez-la inactive plutôt que de la supprimer.",
                422,
            );
        }

        $competence->delete();

        return ApiResponse::success(null, 'Compétence supprimée.');
    }

    /**
     * Supprime plusieurs compétences d'un coup. Même garde-fou que la
     * suppression individuelle, mais appliqué ligne par ligne : une
     * compétence déjà notée est ignorée plutôt que de bloquer tout le lot —
     * l'utilisateur perd un clic, pas la sélection entière.
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucune compétence à supprimer.');
        }

        $competences = Competence::forSchool(Tenant::schoolIds())->whereIn('id', $ids)->get();

        $ignorees = [];
        $supprimees = 0;

        foreach ($competences as $competence) {
            if ($this->notesCount($competence) > 0) {
                $ignorees[] = $competence->label_fr;

                continue;
            }

            $competence->delete();
            $supprimees++;
        }

        $message = "{$supprimees} compétence(s) supprimée(s).";
        if ($ignorees !== []) {
            $message .= ' '.count($ignorees)." déjà notée(s), ignorée(s) : ".implode(', ', $ignorees).'.';
        }

        return ApiResponse::success(['supprimees' => $supprimees, 'ignorees' => $ignorees], $message);
    }

    private function notesCount(Competence $competence): int
    {
        return ClasseCompetence::where('competence_id', $competence->id)
            ->withCount('notes')->get()->sum('notes_count');
    }

    /** Compétences attribuées à une classe, avec leur enseignant et leurs matières. */
    public function parClasse(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $attributions = $classe->classeCompetences()
            ->with(['competence.matieres', 'enseignant'])
            ->get()
            ->sortBy(fn (ClasseCompetence $cc) => [$cc->competence?->ordre, $cc->competence?->label_fr])
            ->values();

        return ApiResponse::success($attributions->map(fn (ClasseCompetence $cc) => [
            'classe_competence_id' => $cc->id,
            'competence' => $cc->competence ? new CompetenceResource($cc->competence) : null,
            'enseignant' => $cc->enseignant
                ? ['id' => $cc->enseignant->id, 'nom_complet' => $cc->enseignant->nom_complet]
                : null,
            'groupe' => $cc->groupe,
            'statut' => $cc->statut,
        ])->values());
    }

    /** Attribue des compétences à une classe ; leurs matières suivent. */
    public function attribuer(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $data = $request->validate([
            'competence_ids' => ['required', 'array', 'min:1'],
            'competence_ids.*' => ['integer', 'exists:competences,id'],
            'personnel_id' => ['nullable', 'integer', 'exists:personnels,id'],
        ]);

        $resultat = $this->attribution->attribuer($classe, $data['competence_ids'], $data['personnel_id'] ?? null);

        return ApiResponse::success(
            $resultat,
            "{$resultat['attribuees']} compétence(s) attribuée(s), {$resultat['matieres']} matière(s) installée(s).",
        );
    }

    /** Change l'enseignant qui tient une compétence dans une classe. */
    public function modifierAttribution(Request $request, int $classeCompetenceId): JsonResponse
    {
        $attribution = ClasseCompetence::forSchool(Tenant::schoolIds())->findOrFail($classeCompetenceId);

        $data = $request->validate([
            'personnel_id' => ['nullable', 'integer', 'exists:personnels,id'],
            'groupe' => ['nullable', 'integer', 'min:1', 'max:9'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ]);

        $attribution->update(array_filter($data, fn ($v) => $v !== null || array_key_exists('personnel_id', $data)));
        $attribution->load(['competence', 'enseignant']);

        return ApiResponse::success([
            'classe_competence_id' => $attribution->id,
            'enseignant' => $attribution->enseignant
                ? ['id' => $attribution->enseignant->id, 'nom_complet' => $attribution->enseignant->nom_complet]
                : null,
            'groupe' => $attribution->groupe,
            'statut' => $attribution->statut,
        ], 'Attribution mise à jour.');
    }

    /** Retire une compétence d'une classe, et les affectations de ses matières. */
    public function retirer(int $classeCompetenceId): JsonResponse
    {
        $attribution = ClasseCompetence::forSchool(Tenant::schoolIds())->findOrFail($classeCompetenceId);

        if ($attribution->notes()->exists()) {
            return ApiResponse::error(
                'Des notes ont déjà été saisies pour cette compétence dans cette classe.',
                422,
            );
        }

        $this->attribution->retirer($attribution);

        return ApiResponse::success(null, 'Compétence retirée de la classe.');
    }
}
