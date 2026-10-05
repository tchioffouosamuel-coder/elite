<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\CompetenceExport;
use App\Exports\ModeleGenerique;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttributionCompetenceRequest;
use App\Http\Requests\Api\V1\StoreCompetenceRequest;
use App\Http\Resources\Api\V1\ClasseCompetenceResource;
use App\Http\Resources\Api\V1\CompetenceResource;
use App\Imports\CompetenceImport;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Services\CompetenceAttributionService;
use App\Services\NotePrimaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Référentiel des compétences évaluées et leur attribution aux classes.
 *
 * Une compétence dit ce que l'on évalue ; les matières en sont le contenu
 * enseigné, et c'est son attribution à une classe qui porte le barème et les
 * volets — la même compétence ne se note pas pareil au CP et au CM2.
 * Attribuer une compétence à une classe y installe d'office ses matières :
 * l'utilisateur choisit un bloc, pas une liste.
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
     * toutes dans sa classe sans être nommé sur chacune ; s'y ajoutent celles
     * qu'on m'a confiées dans une classe que je ne tiens pas
     * ({@see ClasseCompetence::enseignant()}).
     */
    public function mesAffectations(Request $request): JsonResponse
    {
        $personnelId = $request->user()->personnel?->id;

        if ($personnelId === null) {
            return ApiResponse::success([]);
        }

        $attributions = ClasseCompetence::whereHas('classe', fn ($q) => $q->forSchool(Tenant::schoolIds()))
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
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
     * Import du référentiel : une ligne par compétence ET par classe, puisque
     * le barème appartient désormais au couple. Une compétence enseignée dans
     * six classes occupe donc six lignes — c'est le prix d'un barème qui peut
     * varier d'une classe à l'autre, et la forme exacte que ressort l'export.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        // Comme `store()` : en mode agrégé (super admin, « Toutes les écoles »),
        // deviner l'établissement serait arbitraire.
        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $import = new CompetenceImport($schoolId, $this->attribution);
        Excel::import($import, $request->file('file'));

        return ApiResponse::success([
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'attributions' => $import->attributionsCount,
            'failed' => 0,
            // Libellés que l'import n'a pas su rattacher : l'utilisateur
            // corrige son fichier et rejoue, plutôt que de chercher ce qui
            // manque en comparant deux listes.
            'classes_introuvables' => $import->classesIntrouvables,
        ], $this->messageImport($import));
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new CompetenceExport(Tenant::schoolIds()), 'competences.xlsx');
    }

    public function modele(): BinaryFileResponse
    {
        return Excel::download(new ModeleGenerique(CompetenceImport::enTetes()), 'competences-modele.xlsx');
    }

    private function messageImport(CompetenceImport $import): string
    {
        $message = "{$import->importedCount} compétence(s) créée(s), {$import->updatedCount} mise(s) à jour, "
            ."{$import->attributionsCount} attribution(s) de classe enregistrée(s).";

        if ($import->classesIntrouvables !== []) {
            $message .= ' Classes introuvables : '.implode(', ', $import->classesIntrouvables).'.';
        }

        return $message;
    }

    /**
     * Compétences attribuées à une classe, avec leurs matières et l'enseignant
     * qui les tient — celui nommé sur l'attribution, à défaut le titulaire de
     * la classe (cf. `ClasseCompetenceResource`).
     */
    public function parClasse(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $attributions = $classe->classeCompetences()
            ->with(['competence.matieres', 'enseignant', 'classe.titulaire'])
            ->get()
            ->sortBy(fn (ClasseCompetence $cc) => [$cc->competence?->ordre, $cc->competence?->label_fr])
            ->values();

        return ApiResponse::success(ClasseCompetenceResource::collection($attributions));
    }

    /**
     * Attribue des compétences à une classe ; leurs matières suivent.
     *
     * Le barème passé ici s'applique à TOUTES les compétences du lot — c'est
     * le geste courant (« ces huit compétences, sur 20 chacune, réparties
     * ainsi »). Un réglage qui diffère d'une compétence à l'autre se fait
     * ensuite, attribution par attribution, via `modifierAttribution()`.
     */
    public function attribuer(StoreAttributionCompetenceRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);

        $data = $request->validate([
            'competence_ids' => ['required', 'array', 'min:1'],
            'competence_ids.*' => ['integer', 'exists:competences,id'],
        ]);

        $resultat = $this->attribution->attribuer($classe, $data['competence_ids'], $request->bareme());

        return ApiResponse::success(
            $resultat,
            "{$resultat['attribuees']} compétence(s) attribuée(s), {$resultat['matieres']} matière(s) installée(s).",
        );
    }

    /**
     * Change le barème, le groupe ou le statut d'une attribution de compétence.
     *
     * C'est ici que se règle la notation : barème, volet pratique et
     * répartition des points valent pour cette classe seule.
     *
     * C'est aussi ici que se confie la compétence : `personnel_id` nomme
     * l'enseignant qui en est responsable dans cette classe, et `null` la rend
     * au titulaire. Le changement descend sur les matières que la compétence a
     * installées (cf. `CompetenceAttributionService::confier()`), pour qu'un
     * même bloc n'affiche pas deux enseignants selon l'écran.
     */
    public function modifierAttribution(StoreAttributionCompetenceRequest $request, int $classeCompetenceId): JsonResponse
    {
        $attribution = ClasseCompetence::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeCompetenceId);
        $this->autoriserGestionAttribution($request, $attribution);

        $donnees = $request->validated();

        // `array_key_exists` et non `isset` : `personnel_id => null` est la
        // demande explicite de rendre la compétence au titulaire, qu'une mise
        // à jour partielle du seul barème ne doit pas déclencher.
        if (array_key_exists('personnel_id', $donnees)) {
            $personnelId = $donnees['personnel_id'];

            if ($personnelId !== null) {
                $personnel = Personnel::forSchool(Tenant::schoolIds())->find($personnelId);

                if ($personnel === null || $personnel->school_id !== $attribution->classe->school_id) {
                    return ApiResponse::error("Cet agent n'appartient pas à l'école de cette classe.", 422);
                }
            }

            $this->attribution->confier($attribution, $personnelId);
            unset($donnees['personnel_id']);
        }

        // `null` est une valeur de réglage à part entière ici — remettre la
        // répartition à null, c'est revenir au partage à parts égales. Seules
        // les clés absentes de la requête sont écartées, par `validated()`.
        $attribution->update($donnees);
        $attribution->load(['competence', 'enseignant', 'classe.titulaire']);

        return ApiResponse::success(
            new ClasseCompetenceResource($attribution),
            'Attribution mise à jour.',
        );
    }

    /**
     * Recopie des attributions vers d'autres classes — le pendant primaire de
     * `ClasseMatiereController::copier()`.
     *
     * Une classe se règle une fois, puis se reporte sur les sections voisines :
     * c'est le geste que cette route évite de refaire bloc par bloc. Le barème
     * suit ; l'enseignant non, c'est le titulaire de la classe d'arrivée qui
     * tient les compétences qu'elle reçoit.
     */
    public function copier(Request $request): JsonResponse
    {
        $schoolIds = Tenant::schoolIds();

        $data = $request->validate([
            'attribution_ids' => ['required', 'array', 'min:1'],
            'attribution_ids.*' => ['integer'],
            'classe_ids' => ['required', 'array', 'min:1'],
            'classe_ids.*' => ['integer'],
        ]);

        $attributions = ClasseCompetence::forSchool($schoolIds)
            ->with('classe')
            ->whereIn('id', $data['attribution_ids'])
            ->get();
        $classesCibles = Classe::forSchool($schoolIds)->whereIn('id', $data['classe_ids'])->get();

        if ($attributions->isEmpty() || $classesCibles->isEmpty()) {
            return ApiResponse::notFound();
        }

        foreach ($attributions as $attribution) {
            $this->autoriserGestionAttribution($request, $attribution);
        }

        // Une compétence appartient à son école : la recopier dans la classe
        // d'une autre y installerait un bloc du référentiel voisin.
        $horsEcole = $classesCibles->first(
            fn (Classe $classe) => $attributions->contains(fn (ClasseCompetence $a) => $a->classe->school_id !== $classe->school_id),
        );

        if ($horsEcole !== null) {
            return ApiResponse::error("La classe « {$horsEcole->nom} » n'appartient pas à la même école.", 422);
        }

        $resultat = $this->attribution->copier($attributions, $classesCibles);

        return ApiResponse::success(
            $resultat,
            $resultat['ignorees'] > 0
                ? "{$resultat['copiees']} compétence(s) copiée(s), {$resultat['ignorees']} déjà présente(s) ignorée(s)."
                : "{$resultat['copiees']} compétence(s) copiée(s).",
        );
    }

    /**
     * Retire plusieurs compétences d'une classe d'un coup.
     *
     * Même règle que la suppression unitaire, appliquée au lot : dès qu'une
     * seule des compétences porte des notes, l'opération entière demande le
     * mot de passe (409), puis part en bloc — sans quoi l'utilisateur devrait
     * deviner laquelle du lot l'a fait échouer.
     */
    public function batchRetirer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'mot_de_passe' => ['nullable', 'string'],
        ]);

        $attributions = ClasseCompetence::forSchool(Tenant::schoolIds())
            ->with('classe')
            ->whereIn('id', $data['ids'])
            ->get();

        if ($attributions->isEmpty()) {
            return ApiResponse::notFound();
        }

        foreach ($attributions as $attribution) {
            $this->autoriserGestionAttribution($request, $attribution);
        }

        $notees = $attributions->filter(fn (ClasseCompetence $a) => $a->notes()->exists());

        if ($notees->isNotEmpty()) {
            $motDePasse = (string) ($data['mot_de_passe'] ?? '');

            if ($motDePasse === '') {
                return ApiResponse::error(
                    "{$notees->count()} des compétences sélectionnées portent déjà des notes. "
                        .'Confirmez votre mot de passe pour les supprimer quand même, avec leurs affectations et leurs notes.',
                    409,
                );
            }

            if (! Hash::check($motDePasse, $request->user()->password)) {
                return ApiResponse::error('Mot de passe incorrect.', 422);
            }
        }

        foreach ($attributions as $attribution) {
            $this->attribution->retirer($attribution);
        }

        $retirees = $attributions->count();

        return ApiResponse::success(
            ['retirees' => $retirees],
            "{$retirees} compétence(s) retirée(s) de la classe.",
        );
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
     * Le middleware `permission:competences.attribuer` ne borne pas cette route :
     * elle nomme une attribution (`{classeCompetenceId}`), pas une classe
     * qu'il saurait reconnaître. Un animateur de niveau ne tient
     * ce privilège que via son attribution — le vérifier ici évite
     * qu'il modifie les attributions d'un niveau qui n'est pas le sien. Qui
     * détient déjà le privilège de base (admin, censeur) n'est pas concerné.
     */
    private function autoriserGestionAttribution(Request $request, ClasseCompetence $attribution): void
    {
        $user = $request->user();

        if ($user->permissionsDeBase()->contains('competences.attribuer')) {
            return;
        }

        $perimetre = $user->perimetre();
        $niveauScolaireId = $attribution->classe->niveau_scolaire_id ?? -1;

        abort_unless(
            $perimetre->peutSurNiveauScolaire('competences.attribuer', $niveauScolaireId),
            403,
            "Cette compétence n'entre pas dans le niveau que vous animez.",
        );
    }
}
