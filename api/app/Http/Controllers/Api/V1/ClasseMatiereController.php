<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClasseMatiereRequest;
use App\Http\Requests\Api\V1\UpdateClasseMatiereRequest;
use App\Http\Resources\Api\V1\ClasseMatiereResource;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Services\NoteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseMatiereController extends Controller
{
    public function __construct(private readonly NoteService $notes) {}

    public function index(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $affectations = $classe->classeMatieres()->with(['matiere', 'enseignant'])->orderBy('groupe')->get();

        return ApiResponse::success(ClasseMatiereResource::collection($affectations));
    }

    public function store(StoreClasseMatiereRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $affectation = $classe->classeMatieres()
            ->create([...$request->validated(), 'groupe' => $request->input('groupe', 1)])
            ->refresh();

        return ApiResponse::created(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Matière affectée à la classe.');
    }

    public function update(UpdateClasseMatiereRequest $request, int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(Tenant::schoolIds())->with('matiere')->findOrFail($id);
        $this->autoriserGestionAffectation($request, $affectation);
        $affectation->update($request->validated());

        return ApiResponse::success(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Affectation mise à jour.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(Tenant::schoolIds())->with('matiere')->findOrFail($id);
        $this->autoriserGestionAffectation($request, $affectation);
        $affectation->delete();

        return ApiResponse::success(message: 'Matière retirée de la classe.');
    }

    /**
     * Le middleware `permission:pedagogie.manage` ne borne pas cette route :
     * elle nomme une affectation (`{id}`), pas une classe ou un département
     * qu'il saurait reconnaître (cf. VerifierPermission::classeConcernee()).
     * Un chef de département ou un professeur principal ne tiennent
     * `pedagogie.manage` que via leur attribution — le vérifier ici évite que
     * l'un modifie les affectations d'un département qui n'est pas le sien,
     * ou l'autre celles d'une classe dont il n'a pas la charge. Qui détient
     * déjà le privilège de base (admin, censeur) n'est pas concerné : sa
     * portée reste l'école.
     */
    private function autoriserGestionAffectation(Request $request, ClasseMatiere $affectation): void
    {
        $user = $request->user();

        if ($user->permissionsDeBase()->contains('pedagogie.manage')) {
            return;
        }

        $perimetre = $user->perimetre();

        abort_unless(
            $perimetre->peutSurDepartement('pedagogie.manage', $affectation->matiere->departement_id ?? -1)
                || $perimetre->peutSurClasse('pedagogie.manage', $affectation->classe_id),
            403,
            "Cette matière n'entre pas dans votre périmètre de gestion.",
        );
    }

    /**
     * Classes et matières où l'utilisateur connecté enseigne, toute l'année —
     * contrairement à « Ma journée » (`MaJourneeService::mesAffectations`),
     * qui ne garde que les créneaux prévus le jour même. Un enseignant doit
     * pouvoir consulter le remplissage de n'importe laquelle de ses
     * affectations, pas seulement celle programmée aujourd'hui.
     */
    public function mesAffectations(Request $request): JsonResponse
    {
        $personnelId = $request->user()->personnel?->id;

        if ($personnelId === null) {
            return ApiResponse::success([]);
        }

        $affectations = ClasseMatiere::forSchool(Tenant::schoolIds())
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                // Le titulaire du primaire enseigne toutes les matières de sa
                // classe sans être nommé sur chaque affectation.
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->with(['classe', 'matiere'])
            ->get();

        // Remplissage des notes de la séquence active : lu une seule fois pour
        // tout le lot plutôt que par affectation, elle est commune à l'école.
        $sequenceActive = Sequence::whereHas(
            'trimestre',
            fn ($q) => $q->where('is_active', true)->whereHas('anneeScolaire', fn ($aq) => $aq->whereIn('school_id', Tenant::schoolIds()))
        )->first();

        return ApiResponse::success($affectations->map(fn (ClasseMatiere $cm) => [
            'classe_matiere_id' => $cm->id,
            'classe_id' => $cm->classe->id,
            'classe' => $cm->classe->nom,
            'matiere' => $cm->matiere->nom,
            'taux_remplissage' => $sequenceActive ? $this->notes->tauxRemplissage($cm, $sequenceActive->id) : null,
        ])->values());
    }

    /**
     * Duplique une ou plusieurs affectations (matière, enseignant, coefficient,
     * quota horaire) vers une ou plusieurs autres classes — évite de ressaisir
     * à la main la même configuration classe par classe. Une matière déjà
     * affectée dans la classe cible est laissée telle quelle : la copie
     * complète, elle n'écrase jamais un réglage déjà en place.
     *
     * L'enseignant, lui, ne se recopie qu'au secondaire (cf. `enseignantPour`).
     */
    public function copier(Request $request): JsonResponse
    {
        $schoolIds = Tenant::schoolIds();

        $data = $request->validate([
            'affectation_ids' => ['required', 'array', 'min:1'],
            'affectation_ids.*' => ['integer'],
            'classe_ids' => ['required', 'array', 'min:1'],
            'classe_ids.*' => ['integer'],
        ]);

        $affectations = ClasseMatiere::forSchool($schoolIds)->whereIn('id', $data['affectation_ids'])->get();
        $classesCibles = Classe::forSchool($schoolIds)
            ->with('school:id,type')
            ->whereIn('id', $data['classe_ids'])
            ->get();

        if ($affectations->isEmpty() || $classesCibles->isEmpty()) {
            return ApiResponse::notFound();
        }

        $copiees = 0;
        $ignorees = 0;

        foreach ($classesCibles as $classe) {
            $matieresExistantes = $classe->classeMatieres()->pluck('matiere_id');

            foreach ($affectations as $source) {
                if ($matieresExistantes->contains($source->matiere_id)) {
                    $ignorees++;

                    continue;
                }

                $classe->classeMatieres()->create([
                    'matiere_id' => $source->matiere_id,
                    'personnel_id' => $this->enseignantPour($classe, $source),
                    'coefficient' => $source->coefficient,
                    'quota_horaire' => $source->quota_horaire,
                    'groupe' => $source->groupe,
                    'competences' => $source->competences,
                ]);
                $copiees++;
            }
        }

        $message = $ignorees > 0
            ? "{$copiees} affectation(s) copiée(s), {$ignorees} déjà présente(s) ignorée(s)."
            : "{$copiees} affectation(s) copiée(s).";

        return ApiResponse::success(['copiees' => $copiees, 'ignorees' => $ignorees], $message);
    }

    /**
     * Change l'enseignant de plusieurs affectations d'un coup.
     *
     * Une matière transversale — éducation civique, sport, informatique — se
     * retrouve dans toutes les classes d'un niveau : reprendre son professeur
     * ligne par ligne est le geste que cette route supprime.
     *
     * `personnel_id` nul détache l'enseignant, ce qui est le seul moyen de
     * corriger une affectation posée sur le mauvais agent.
     */
    public function batchEnseignant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'personnel_id' => ['nullable', 'integer', 'exists:personnels,id'],
        ]);

        $affectations = ClasseMatiere::forSchool(Tenant::schoolIds())
            ->with('classe:id,school_id')
            ->whereIn('id', $data['ids'])
            ->get();

        if ($affectations->isEmpty()) {
            return ApiResponse::notFound();
        }

        $personnel = $data['personnel_id'] === null
            ? null
            : Personnel::forSchool(Tenant::schoolIds())->find($data['personnel_id']);

        if ($data['personnel_id'] !== null && $personnel === null) {
            return ApiResponse::error("Cet agent n'appartient pas à votre établissement.", 422);
        }

        $modifiees = 0;
        $ignorees = 0;

        foreach ($affectations as $affectation) {
            // Un enseignant ne peut prendre une classe d'une autre école : la
            // sélection peut couvrir plusieurs écoles en mode agrégé.
            if ($personnel !== null && $affectation->classe?->school_id !== $personnel->school_id) {
                $ignorees++;

                continue;
            }

            $affectation->update(['personnel_id' => $personnel?->id]);
            $modifiees++;
        }

        $message = $personnel === null
            ? "{$modifiees} affectation(s) sans enseignant."
            : "{$modifiees} affectation(s) confiée(s) à {$personnel->nom_complet}.";

        return ApiResponse::success(
            ['modifiees' => $modifiees, 'ignorees' => $ignorees],
            $ignorees > 0 ? $message." {$ignorees} ignorée(s) : classe d'une autre école." : $message,
        );
    }

    /**
     * Qui enseignera la matière copiée dans la classe d'arrivée.
     *
     * Au primaire et en maternelle, un seul enseignant — le titulaire — tient
     * toutes les matières de sa classe. Y recopier l'enseignant de la classe
     * source désignerait un agent qui n'y met pas les pieds, et il faudrait
     * corriger chaque ligne à la main juste après la copie. C'est donc le
     * titulaire de la classe de destination qui prend la matière ; si elle n'en
     * a pas encore, la colonne reste vide plutôt que fausse.
     *
     * Le secondaire garde le comportement inverse, et pour la même raison de
     * fond : la matière y suit son professeur spécialiste, qui enseigne le même
     * programme dans plusieurs classes d'un même niveau.
     *
     * Le type est lu sur l'école de LA classe cible, pas sur l'école active :
     * en mode agrégé (super admin, « Toutes les écoles ») il n'y en a pas
     * d'unique, et un complexe copie couramment d'une école vers une autre.
     */
    private function enseignantPour(Classe $classe, ClasseMatiere $source): ?int
    {
        if ($classe->school?->estSecondaire()) {
            return $source->personnel_id;
        }

        return $classe->titulaire_id;
    }
}
