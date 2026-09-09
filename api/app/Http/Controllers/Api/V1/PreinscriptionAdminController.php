<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ModeleGenerique;
use App\Exports\NonInscritExport;
use App\Exports\PreinscriptionExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Imports\PreinscriptionImport;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Services\PreinscriptionService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** File d'attente des préinscriptions déposées par les parents, à valider ou rejeter. */
class PreinscriptionAdminController extends Controller
{
    public function __construct(private readonly PreinscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $preinscriptions = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule'])
            ->when($request->string('statut')->toString(), fn($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($preinscriptions->map(fn(Preinscription $p) => $this->resume($p)));
    }

    public function schema(): JsonResponse
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $tableName = is_array($table) ? $table['name'] : $table->name;

            $tables[$tableName] = [
                'columns' => array_map(
                    static fn(mixed $column): array => (array) $column,
                    Schema::getColumns($tableName),
                ),
                'indexes' => array_map(
                    static fn(mixed $index): array => (array) $index,
                    Schema::getIndexes($tableName),
                ),
                'foreign_keys' => array_map(
                    static fn(mixed $foreignKey): array => (array) $foreignKey,
                    Schema::getForeignKeys($tableName),
                ),
            ];
        }

        return ApiResponse::success([
            'tables' => $tables,
        ]);
    }

    public function migrations(): JsonResponse
    {
        $migrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->get(['migration', 'batch']);

        return ApiResponse::success([
            'migrations' => $migrations,
            'total' => $migrations->count(),
            'dernier_batch' => $migrations->max('batch'),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule,classe_id', 'eleve.classe:id,nom'])
            ->findOrFail($id);

        return ApiResponse::success([
            ...$this->resume($p),
            'donnees_eleve' => $p->donnees_eleve,
            'donnees_tuteurs' => $p->donnees_tuteurs,
            'note_admin' => $p->note_admin,
            'mode_versement' => $p->mode_versement,
            'reference_externe' => $p->reference_externe,
            'rubriques_versement' => $p->rubriques_versement,
            'classe_actuelle' => $p->eleve?->classe?->nom,
            'classe_id' => $p->classe_id,
        ]);
    }

    /**
     * Préinscription créée par l'admin lui-même, pour un élève déjà connu du
     * système (réinscription au guichet) — saisie et validée du même geste,
     * sans passer par la file d'attente « en attente ». Cf.
     * `PreinscriptionService::creerEtValiderParAdmin()`.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eleve_id' => ['required', 'integer'],

            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.telephones' => ['nullable', 'array'],
            'donnees_tuteurs.*.telephones.*.numero' => ['required_with:donnees_tuteurs.*.telephones', 'string', 'max:30'],
            'donnees_tuteurs.*.telephones.*.is_principal' => ['nullable', 'boolean'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],

            // Classe visée par la réinscription — laisser vide garde la
            // classe actuelle de l'élève inchangée (cf. PreinscriptionService::valider).
            'classe_id' => ['nullable', 'integer', 'exists:classes,id'],

            'montant_verser' => ['nullable', 'integer', 'min:1'],
            'mode_versement' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'rubriques_versement' => ['nullable', 'array', 'min:1'],
            'rubriques_versement.*.affectation' => ['required_with:rubriques_versement', 'in:scolarite,frais_annexe,report_dette'],
            'rubriques_versement.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'rubriques_versement.*.libelle' => ['nullable', 'string', 'max:150'],
            'rubriques_versement.*.montant' => ['required_with:rubriques_versement', 'integer', 'min:1'],
        ]);

        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($data['eleve_id']);

        try {
            $p = $this->service->creerEtValiderParAdmin($eleve, $data, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created(
            $this->resume($p->load('eleve:id,nom_complet,matricule')),
            'Préinscription enregistrée et validée.'
        );
    }

    public function storeNouveau(Request $request): JsonResponse
    {
        $data = $request->validate([
            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.telephones' => ['nullable', 'array'],
            'donnees_tuteurs.*.telephones.*.numero' => ['required_with:donnees_tuteurs.*.telephones', 'string', 'max:30'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],
            'classe_id' => ['nullable', 'integer', 'exists:classes,id'],
            'montant_verser' => ['nullable', 'integer', 'min:1'],
            'mode_versement' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $p = $this->service->creerEtValiderNouveauParAdmin(app('tenant.school_id'), $data, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resume($p->load('eleve:id,nom_complet,matricule')), 'Préinscription enregistrée et validée.');
    }

    /** Corrige les informations proposées par le parent avant validation (coquille, champ oublié…). */
    public function update(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);

        $data = $request->validate([
            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],
            // Pour une nouvelle inscription : la classe proposée par le
            // parent vit ici, et reste directement modifiable par l'admin.
            'donnees_eleve.classe_id' => ['nullable', 'integer', 'exists:classes,id'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.telephones' => ['nullable', 'array'],
            'donnees_tuteurs.*.telephones.*.numero' => ['required_with:donnees_tuteurs.*.telephones', 'string', 'max:30'],
            'donnees_tuteurs.*.telephones.*.is_principal' => ['nullable', 'boolean'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],

            // Pour une réinscription : classe cible distincte de celle
            // actuelle de l'élève, laissée vide si l'admin ne la change pas.
            'classe_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        try {
            $p = $this->service->modifierDonnees($p, $data['donnees_eleve'], $data['donnees_tuteurs'], [
                'classe_id' => $data['classe_id'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            ...$this->resume($p),
            'donnees_eleve' => $p->donnees_eleve,
            'donnees_tuteurs' => $p->donnees_tuteurs,
            'classe_id' => $p->classe_id,
        ], 'Informations mises à jour.');
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $p = $this->service->valider($p, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p->load('eleve:id,nom_complet,matricule')), 'Préinscription validée.');
    }

    /** Anciens élèves qui ne se sont pas encore réinscrits pour l'année scolaire active. */
    public function nonInscrits(): JsonResponse
    {
        $eleves = $this->service->listeAnciensNonReinscrits(Tenant::schoolIds())->load(['classe', 'tuteurs']);

        return ApiResponse::success($eleves->map(fn(Eleve $e) => [
            'id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nom_complet,
            'classe' => $e->classe?->nom,
            'tuteur' => $e->tuteurs->first()?->nom_complet,
            'telephone' => $e->tuteurs->first()?->telephone,
        ])->values());
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new PreinscriptionExport(Tenant::schoolIds(), $this->service), 'preinscriptions.xlsx');
    }

    public function exportNonInscrits(): BinaryFileResponse
    {
        return Excel::download(new NonInscritExport(Tenant::schoolIds(), $this->service), 'eleves-non-inscrits.xlsx');
    }

    public function modele(): BinaryFileResponse
    {
        return Excel::download(new ModeleGenerique(PreinscriptionImport::enTetes()), 'modele-preinscriptions.xlsx');
    }

    /** Import massif d'une campagne de réinscription — chaque ligne validée immédiatement, cf. `PreinscriptionService::importerLigne()`. */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annees_scolaires,id'],
        ]);

        $schoolId = Tenant::schoolId();
        $this->service->verifierAnneeScolaire($schoolId, $data['annee_scolaire_id'] ?? null);
        $import = new PreinscriptionImport($schoolId, $this->service, $request->user()->id, $data['annee_scolaire_id'] ?? null);
        Excel::import($import, $request->file('file'));

        // `imported`/`failed` : mêmes clés que les autres imports de l'appli
        // (cf. EleveController::import()), pour rester compatible avec le
        // composant générique `ImportModal` côté web sans lui apprendre un
        // nouveau format de réponse.
        return ApiResponse::success([
            'imported' => $import->importees,
            'failed' => count($import->erreurs),
            'erreurs' => $import->erreurs,
        ], "{$import->importees} préinscription(s) importée(s) et validée(s).");
    }

    /**
     * Dépose le fichier et le découpe en lots — cf.
     * `PreinscriptionService::preparerImportDecoupe()`. Un fichier de
     * plusieurs centaines de lignes envoyé en un seul appel à `import()`
     * dépasse le délai d'exécution du serveur ; le client traite ensuite
     * chaque lot séparément via `importerLot()`.
     */
    public function importPreparer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annees_scolaires,id'],
        ]);

        $this->service->verifierAnneeScolaire(Tenant::schoolId(), $data['annee_scolaire_id'] ?? null);
        $token = (string) Str::uuid();
        $lots = $this->service->preparerImportDecoupe($request->file('file'), $token);

        return ApiResponse::success(['token' => $token, 'lots' => $lots]);
    }

    public function importerLot(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annees_scolaires,id'],
        ]);

        try {
            $schoolId = Tenant::schoolId();
            $this->service->verifierAnneeScolaire($schoolId, $data['annee_scolaire_id'] ?? null);
            ['resultat' => $resultat, 'dernier' => $dernier] = $this->service->importerChunk(
                $schoolId,
                $token,
                $data['index'],
                $request->user()->id,
                $data['annee_scolaire_id'] ?? null,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([...$resultat, 'dernier' => $dernier]);
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $p = $this->service->rejeter($p, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p), 'Préinscription rejetée.');
    }

    private function resume(Preinscription $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'statut' => $p->statut,
            'tuteur' => $p->tuteur ? ['id' => $p->tuteur->id, 'nom_complet' => $p->tuteur->nom_complet, 'telephone' => $p->tuteur->telephone, 'email' => $p->tuteur->email] : null,
            'eleve' => $p->eleve ? ['id' => $p->eleve->id, 'nom_complet' => $p->eleve->nom_complet, 'matricule' => $p->eleve->matricule] : null,
            'nom_propose' => $p->donnees_eleve['nom_complet'] ?? null,
            'montant_verser' => $p->montant_verser,
            'versement_id' => $p->versement_id,
            'motif_rejet' => $p->motif_rejet,
            'created_at' => $p->created_at->format('Y-m-d H:i'),
            'traite_le' => $p->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
