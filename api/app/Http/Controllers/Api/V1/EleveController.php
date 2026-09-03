<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EleveExport;
use App\Exports\ModeleGenerique;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEleveRequest;
use App\Http\Requests\Api\V1\UpdateEleveRequest;
use App\Http\Resources\Api\V1\EleveResource;
use App\Models\ActivityLog;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\Setting;
use App\Services\AuthService;
use App\Services\CompteEleveService;
use App\Services\EleveService;
use App\Services\SettingsCatalog;
use App\Support\Pdf\IdentifiantsGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class EleveController extends Controller
{
    public function __construct(
        private readonly EleveService $service,
        private readonly CompteEleveService $comptes,
        private readonly AuthService $auth,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->user(),
            Tenant::schoolIds(),
            $request->only(['search', 'classe_id', 'sexe', 'statut']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, EleveResource::class);
    }

    /**
     * Recherche transverse d'élèves, tous critères confondus : nom de
     * l'élève, matricule, ou nom/téléphone d'un de ses tuteurs. Pensée pour
     * une barre de recherche rapide (ex. un appel entrant dont on n'a que le
     * numéro), pas pour remplacer la liste filtrée — d'où la réponse en
     * liste simple, non paginée, plutôt qu'un LengthAwarePaginator.
     */
    public function rechercheGlobale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $eleves = $this->service->rechercheGlobale($request->user(), Tenant::schoolIds(), $data['q']);

        return ApiResponse::success(EleveResource::collection($eleves));
    }

    /**
     * L'école d'un élève découle de sa classe, pas d'un champ dédié : en
     * l'absence de classe (élève non encore affecté), on retombe sur
     * Tenant::resolveWriteSchoolId() qui exige alors un school_id explicite
     * en mode agrégé plutôt que de deviner.
     */
    public function store(StoreEleveRequest $request): JsonResponse
    {
        $data = $request->validated();

        $schoolId = isset($data['classe_id'])
            ? Classe::forSchool(Tenant::schoolIds())->findOrFail($data['classe_id'])->school_id
            : Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $eleve = $this->service->create($schoolId, $data);

        ActivityLog::enregistrer($request->user(), 'eleve.cree', "Inscription de {$eleve->nom_complet}.", $eleve);

        return ApiResponse::created(new EleveResource($eleve), 'Élève inscrit.');
    }

    public function show(int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);

        return ApiResponse::success(new EleveResource($eleve));
    }

    public function update(UpdateEleveRequest $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->update($eleve, $request->validated());

        return ApiResponse::success(new EleveResource($eleve), 'Élève mis à jour.');
    }

    public function repartition(): JsonResponse
    {
        return ApiResponse::success($this->service->repartition(Tenant::schoolIds()));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $classeId = $request->integer('classe_id') ?: null;

        return Excel::download(new EleveExport(Tenant::schoolIds(), $classeId), 'eleves.xlsx');
    }

    public function modele(): BinaryFileResponse
    {
        return Excel::download(new ModeleGenerique(\App\Imports\EleveImport::enTetes()), 'modele-eleves.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'progress_token' => ['nullable', 'uuid'],
        ]);
        $progressToken = $request->string('progress_token')->toString() ?: null;
        $progress = $progressToken
            ? function (int $processed, int $total, string $name) use ($progressToken): void {
                Cache::put('eleves-import:' . $progressToken, [
                    'processed' => $processed,
                    'total' => $total,
                    'current_name' => $name,
                ], now()->addMinutes(30));
            }
            : null;

        // En mode agrégé (super admin sans X-School-Id), chaque ligne rejoint son
        // école d'après categorie_ecole plutôt que de toutes atterrir dans une
        // seule — cf. EleveService::importPourToutesLesEcoles().
        $schoolId = Tenant::isAggregate() ? Tenant::schoolIds() : Tenant::schoolId();

        try {
            $result = $this->service->importFromExcel($schoolId, $request->file('file'), $request->user()?->id, $progress);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $message = "{$result['imported']} élève(s) créé(s), {$result['updated']} mis à jour.";

        if ($result['dettes'] > 0) {
            $message .= ' ' . $result['dettes'] . " dette(s) antérieure(s) reprise(s) (" . number_format($result['dettes_montant'], 0, ',', ' ') . " FCFA).";
        }

        return ApiResponse::success($result, $message);
    }

    public function importProgress(string $token): JsonResponse
    {
        return ApiResponse::success(Cache::get('eleves-import:' . $token, [
            'processed' => 0,
            'total' => 0,
            'current_name' => null,
        ]));
    }

    /**
     * Découpe un fichier de situation en petits lots avant l'import — pour un
     * gros effectif, une seule requête synchrone dépasserait facilement le
     * délai d'exécution du serveur. Le client importe ensuite chaque lot par
     * son propre appel à `importerLot()`, sans jamais renvoyer le fichier
     * entier (cf. EleveService::preparerImportDecoupe).
     */
    public function importPreparer(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $token = (string) Str::uuid();
        $lots = $this->service->preparerImportDecoupe($request->file('file'), $token);

        return ApiResponse::success(['token' => $token, 'lots' => $lots]);
    }

    public function importerLot(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $schoolId = Tenant::isAggregate() ? Tenant::schoolIds() : Tenant::schoolId();

        try {
            ['resultat' => $result, 'dernier' => $dernier] = $this->service->importerChunk(
                $schoolId, $token, $data['index'], $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([...$result, 'dernier' => $dernier]);
    }

    public function photo(Request $request, int $id): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->updatePhoto($eleve, $request->file('photo'));

        return ApiResponse::success(new EleveResource($eleve), 'Photo mise à jour.');
    }

    /**
     * Transfert d'un élève vers une autre école du complexe. La classe d'arrivée
     * est obligatoire : sans elle l'élève se retrouverait dans un établissement
     * où il n'est rattaché à aucun enseignement, invisible des listes de classe.
     */
    public function transfert(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return ApiResponse::forbidden('Seul le super administrateur peut transférer un élève entre établissements.');
        }

        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'classe_id' => ['required', 'integer'],
        ]);

        if (! $user->ecolesAccessibles()->contains('id', $data['school_id'])) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        $classe = Classe::where('school_id', $data['school_id'])->find($data['classe_id']);

        if (! $classe) {
            return ApiResponse::error("La classe d'arrivée n'appartient pas à l'établissement de destination.", 422);
        }

        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->transferer($eleve, $classe);

        return ApiResponse::success(
            new EleveResource($eleve),
            'Élève transféré vers ' . $classe->school->name . ' — ' . $classe->nom . '.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $this->service->delete($eleve);

        return ApiResponse::success(null, 'Élève supprimé.');
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $schoolId = Tenant::schoolIds();
        $deleted = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->delete($eleve);
            $deleted++;
        }

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} élève(s) supprimé(s).");
    }

    /** Bascule un lot d'élèves vers une autre classe de la même école. */
    public function batchTransfertClasse(Request $request): JsonResponse
    {
        $schoolId = Tenant::schoolIds();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'classe_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
        ]);

        $transferes = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->update($eleve, ['classe_id' => $data['classe_id']]);
            $transferes++;
        }

        return ApiResponse::success(['transferes' => $transferes], "{$transferes} élève(s) transféré(s).");
    }

    /**
     * Bascule un lot d'élèves vers une autre école du complexe — même
     * restriction que le transfert unitaire : seul le super administrateur y
     * est autorisé, et la classe d'arrivée doit appartenir à l'école visée.
     */
    public function batchTransfertEcole(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return ApiResponse::forbidden('Seul le super administrateur peut transférer des élèves entre établissements.');
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'school_id' => ['required', 'integer'],
            'classe_id' => ['required', 'integer'],
        ]);

        if (! $user->ecolesAccessibles()->contains('id', $data['school_id'])) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        $classe = Classe::where('school_id', $data['school_id'])->find($data['classe_id']);

        if (! $classe) {
            return ApiResponse::error("La classe d'arrivée n'appartient pas à l'établissement de destination.", 422);
        }

        $schoolId = Tenant::schoolIds();
        $transferes = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->transferer($eleve, $classe);
            $transferes++;
        }

        return ApiResponse::success(['transferes' => $transferes], "{$transferes} élève(s) transféré(s) vers {$classe->school->name} — {$classe->nom}.");
    }

    /** Ouvre l'accès élève (portail lecture seule) — pendant de {@see TuteurController::creerCompteParent()}. */
    public function creerCompteEleve(int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $user = $this->comptes->assurer($eleve);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'user_id' => $user->id,
            'identifiant' => $eleve->matricule,
            'mot_de_passe_provisoire' => $user->doit_changer_mot_de_passe
                ? Setting::get($eleve->school_id, 'mot_de_passe_defaut', SettingsCatalog::default('mot_de_passe_defaut'))
                : null,
        ], 'Accès élève ouvert.');
    }

    /** Ouvre l'accès de tous les élèves de l'école qui n'en ont pas encore — pendant de {@see TuteurController::creerComptesParentLot()}. */
    public function creerComptesEleveLot(Request $request): JsonResponse
    {
        $schoolId = $request->integer('school_id') ?: null;
        $schoolIds = $schoolId !== null ? [Tenant::resolveWriteSchoolId($schoolId)] : Tenant::schoolIds();

        $resultat = $this->comptes->assurerLot($schoolIds);

        $message = $resultat['crees'] > 0
            ? "{$resultat['crees']} accès élève ouvert(s)."
            : 'Aucun nouvel accès à ouvrir — tous les élèves avec un matricule valide en ont déjà un.';

        return ApiResponse::success($resultat, $message);
    }

    /** Bloque/débloque l'accès élève — pendant de {@see TuteurController::basculerAcces()}. */
    public function basculerAcces(int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->with('user')->findOrFail($id);

        if (! $eleve->user) {
            return ApiResponse::error("Cet élève n'a pas encore de compte.", 422);
        }

        $eleve->user->update(['is_active' => ! $eleve->user->is_active]);

        if (! $eleve->user->is_active) {
            $this->auth->revoquerTousLesJetons($eleve->user);
        }

        return ApiResponse::success(null, $eleve->user->is_active ? 'Accès élève débloqué.' : 'Accès élève bloqué.');
    }

    /** Supprime le compte élève (le portail, pas la fiche) — pendant de {@see TuteurController::supprimerCompteParent()}. */
    public function supprimerCompteEleve(int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->with('user')->findOrFail($id);

        if ($eleve->user) {
            $eleve->user->delete();
            $eleve->forceFill(['user_id' => null])->save();
        }

        return ApiResponse::success(null, 'Compte élève supprimé.');
    }

    /** Document PDF des identifiants élèves — pendant de {@see TuteurController::identifiantsParentPdf()}. */
    public function identifiantsElevePdf(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn (School $school) => [
                'donnees' => $this->comptes->identifiants($school->id),
                'school' => $school,
            ])->all();
            $pdf = (new IdentifiantsGenerator)->buildMany($documents);
            $nom = 'identifiants-eleves-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $pdf = (new IdentifiantsGenerator)->build($this->comptes->identifiants($school->id), $school);
            $nom = 'identifiants-eleves-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }
}
