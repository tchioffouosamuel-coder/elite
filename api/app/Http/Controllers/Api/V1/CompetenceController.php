<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompetenceRequest;
use App\Http\Resources\Api\V1\CompetenceResource;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use App\Models\Sequence;
use App\Services\CompetenceAttributionService;
use App\Services\NotePrimaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Référentiel des compétences évaluées et leur attribution aux classes.
 *
 * Une compétence porte le barème et les volets ; les matières en sont le
 * contenu. Attribuer une compétence à une classe y installe d'office ses
 * matières — l'utilisateur choisit un bloc, pas une liste.
 */
class CompetenceController extends Controller
{
    public function __construct(
        private readonly CompetenceAttributionService $attribution,
        private readonly NotePrimaireService $notes,
    ) {}

    /**
     * Compétences que je tiens, toutes classes confondues — pendant primaire
     * de `ClasseMatiereController::mesAffectations()`. Le titulaire les tient
     * toutes dans sa classe sans être nommé sur chacune.
     */
    public function mesAffectations(Request $request): JsonResponse
    {
        $personnelId = $request->user()->personnel?->id;

        if ($personnelId === null) {
            return ApiResponse::success([]);
        }

        $attributions = ClasseCompetence::whereHas('classe', fn ($q) => $q->forSchool(Tenant::schoolIds()))
            ->where('statut', 'actif')
            ->whereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId))
            ->with(['classe', 'competence'])
            ->get();

        $sequenceActive = Sequence::whereHas(
            'trimestre',
            fn ($q) => $q->where('is_active', true)->whereHas('anneeScolaire', fn ($aq) => $aq->whereIn('school_id', Tenant::schoolIds()))
        )->first();

        return ApiResponse::success($attributions->map(fn (ClasseCompetence $cc) => [
            'classe_competence_id' => $cc->id,
            'classe_id' => $cc->classe->id,
            'classe' => $cc->classe->nom,
            'competence' => $cc->competence->label_fr,
            'taux_remplissage' => $sequenceActive ? $this->notes->tauxRemplissage($cc, $sequenceActive) : null,
        ])->values());
    }

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
     * Supprime une compétence, et avec elle ses matières, ses attributions aux
     * classes et leurs notes — la cascade est posée en base.
     *
     * Si des notes existent déjà, l'opération n'est plus bloquée à sec : elle
     * exige la confirmation du mot de passe de l'utilisateur, sur le même
     * principe que `retirer()` ci-dessous — l'action est destructrice et
     * irréversible. Sans mot de passe fourni, on répond 409 plutôt que 422 :
     * ce n'est pas une erreur de saisie, c'est une étape supplémentaire que le
     * client doit proposer.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $competence = Competence::forSchool(Tenant::schoolIds())->findOrFail($id);

        $notes = $this->notesCount($competence);

        if ($notes > 0) {
            $motDePasse = (string) $request->input('mot_de_passe', '');

            if ($motDePasse === '') {
                return ApiResponse::error(
                    "Cette compétence porte déjà {$notes} note(s). Confirmez votre mot de passe pour la "
                        . 'supprimer quand même, avec ses matières, ses attributions et ses notes.',
                    409,
                );
            }

            if (! Hash::check($motDePasse, $request->user()->password)) {
                return ApiResponse::error('Mot de passe incorrect.', 422);
            }
        }

        // La suppression cascade en base sur les matières, les attributions
        // aux classes et leurs notes (`matieres.competence_id`,
        // `classe_competences.competence_id`, `notes.classe_competence_id` →
        // cascadeOnDelete), les données enfants partent donc avant la ligne
        // parente dans la même opération.
        $competence->delete();

        return ApiResponse::success(null, 'Compétence supprimée.');
    }

    /**
     * Supprime plusieurs compétences d'un coup. Même garde-fou que la
     * suppression individuelle : si l'une des compétences sélectionnées porte
     * déjà des notes, tout le lot exige la confirmation du mot de passe avant
     * de supprimer.
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucune compétence à supprimer.');
        }

        $competences = Competence::forSchool(Tenant::schoolIds())->whereIn('id', $ids)->get();

        $competencesNotees = $competences->filter(fn (Competence $c) => $this->notesCount($c) > 0);

        if ($competencesNotees->isNotEmpty()) {
            $motDePasse = (string) $request->input('mot_de_passe', '');

            if ($motDePasse === '') {
                return ApiResponse::error(
                    count($competencesNotees).' compétence(s) portent déjà des notes. Confirmez votre mot '
                        . 'de passe pour les supprimer quand même, avec leurs matières, attributions et notes.',
                    409,
                );
            }

            if (! Hash::check($motDePasse, $request->user()->password)) {
                return ApiResponse::error('Mot de passe incorrect.', 422);
            }
        }

        foreach ($competences as $competence) {
            $competence->delete();
        }

        $supprimees = $competences->count();

        return ApiResponse::success(['supprimees' => $supprimees], "{$supprimees} compétence(s) supprimée(s).");
    }

    private function notesCount(Competence $competence): int
    {
        return ClasseCompetence::where('competence_id', $competence->id)
            ->withCount('notes')->get()->sum('notes_count');
    }

    /**
     * Compétences attribuées à une classe, avec leurs matières. L'enseignant
     * ne vit plus à ce niveau : il s'affecte par matière, via
     * `ClasseMatiereController` (`GET classes/{id}/matieres`).
     */
    public function parClasse(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $attributions = $classe->classeCompetences()
            ->with('competence.matieres')
            ->get()
            ->sortBy(fn (ClasseCompetence $cc) => [$cc->competence?->ordre, $cc->competence?->label_fr])
            ->values();

        return ApiResponse::success($attributions->map(fn (ClasseCompetence $cc) => [
            'classe_competence_id' => $cc->id,
            'competence' => $cc->competence ? new CompetenceResource($cc->competence) : null,
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
        ]);

        $resultat = $this->attribution->attribuer($classe, $data['competence_ids']);

        return ApiResponse::success(
            $resultat,
            "{$resultat['attribuees']} compétence(s) attribuée(s), {$resultat['matieres']} matière(s) installée(s).",
        );
    }

    /**
     * Change le groupe ou le statut d'une attribution de compétence.
     *
     * L'enseignant ne se change plus ici : il s'affecte par matière, via
     * `ClasseMatiereController::update()`/`batchEnseignant()`.
     */
    public function modifierAttribution(Request $request, int $classeCompetenceId): JsonResponse
    {
        $attribution = ClasseCompetence::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeCompetenceId);
        $this->autoriserGestionAttribution($request, $attribution);

        $data = $request->validate([
            'groupe' => ['nullable', 'integer', 'min:1', 'max:9'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ]);

        $attribution->update(array_filter($data, fn ($v) => $v !== null));
        $attribution->load('competence');

        return ApiResponse::success([
            'classe_competence_id' => $attribution->id,
            'groupe' => $attribution->groupe,
            'statut' => $attribution->statut,
        ], 'Attribution mise à jour.');
    }

    /**
     * Retire une compétence d'une classe, et les affectations de ses matières.
     *
     * Si des notes existent déjà, l'opération n'est plus bloquée à sec : elle
     * exige la confirmation du mot de passe de l'utilisateur, pour la même
     * raison qu'un virement ou une suppression de compte la demandent
     * ailleurs — l'action est destructrice (les notes partent avec, en
     * cascade sur `classe_competences.id`) et irréversible. Sans mot de passe
     * fourni, on répond 409 plutôt que 422 : ce n'est pas une erreur de
     * saisie, c'est une étape supplémentaire que le client doit proposer.
     */
    public function retirer(Request $request, int $classeCompetenceId): JsonResponse
    {
        $attribution = ClasseCompetence::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeCompetenceId);
        $this->autoriserGestionAttribution($request, $attribution);

        if ($attribution->notes()->exists()) {
            $motDePasse = (string) $request->input('mot_de_passe', '');

            if ($motDePasse === '') {
                return ApiResponse::error(
                    'Des notes ont déjà été saisies pour cette compétence dans cette classe. '
                        . 'Confirmez votre mot de passe pour la supprimer quand même, avec ses affectations et ses notes.',
                    409,
                );
            }

            if (! Hash::check($motDePasse, $request->user()->password)) {
                return ApiResponse::error('Mot de passe incorrect.', 422);
            }
        }

        // La suppression de l'attribution cascade en base sur ses notes
        // (`notes.classe_competence_id` → cascadeOnDelete) ; le service
        // retire en plus les affectations de matières qu'elle avait installées.
        $this->attribution->retirer($attribution);

        return ApiResponse::success(null, 'Compétence retirée de la classe.');
    }

    /**
     * Le middleware `permission:pedagogie.manage` ne borne pas cette route :
     * elle nomme une attribution (`{classeCompetenceId}`), pas une classe
     * qu'il saurait reconnaître. Un animateur de niveau ne tient
     * `pedagogie.manage` que via son attribution — le vérifier ici évite
     * qu'il modifie les attributions d'un niveau qui n'est pas le sien. Qui
     * détient déjà le privilège de base (admin, censeur) n'est pas concerné.
     */
    private function autoriserGestionAttribution(Request $request, ClasseCompetence $attribution): void
    {
        $user = $request->user();

        if ($user->permissionsDeBase()->contains('pedagogie.manage')) {
            return;
        }

        $perimetre = $user->perimetre();
        $niveauScolaireId = $attribution->classe->niveau_scolaire_id ?? -1;

        abort_unless(
            $perimetre->peutSurNiveauScolaire('pedagogie.manage', $niveauScolaireId),
            403,
            "Cette compétence n'entre pas dans le niveau que vous animez.",
        );
    }
}
