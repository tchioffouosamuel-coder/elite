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
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\HistoriqueScolariteEleve;
use App\Models\School;
use App\Models\Setting;
use App\Services\AuthService;
use App\Services\CompteEleveService;
use App\Services\EleveFusionService;
use App\Services\EleveService;
use App\Services\PreinscriptionService;
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
        private readonly PreinscriptionService $preinscriptions,
        private readonly CompteEleveService $comptes,
        private readonly AuthService $auth,
        private readonly EleveFusionService $fusion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->user(),
            Tenant::schoolIds(),
            [
                ...$request->only(['search', 'classe_id', 'sexe', 'statut']),
                // Par défaut, seuls les élèves préinscrits pour l'année active
                // apparaissent (cf. Eleve::scopePreinscritAnneeActive()) — les
                // écrans qui doivent voir tout le monde (tableau de bord, page
                // des dettes antérieures, outils de correction de données)
                // passent `tous=1` pour lever ce filtre.
                'tous' => $request->boolean('tous'),
            ],
            (int) $request->integer('per_page', 20),
        );

        $this->marquerNonReinscrits($paginator->getCollection());
        $this->marquerTotalVersements($paginator->getCollection());
        $paginator->getCollection()->each(fn(Eleve $eleve) => $eleve->setAttribute(
            'preinscription_active',
            $this->preinscriptions->estPreinscritAnneeActive($eleve),
        ));

        return ApiResponse::paginated($paginator, EleveResource::class);
    }

    /**
     * Fusionne les doublons certains : même école, même nom (normalisé) et
     * même date de naissance renseignée — signature qui, en pratique, ne
     * désigne quasiment jamais deux enfants distincts, mais une même fiche
     * réimportée sous un nouveau schéma de matricule.
     *
     * Un groupe où aucune fiche (ou plusieurs) n'est rattachée à une classe
     * cette année est ambigu — impossible de désigner la fiche à conserver
     * sans arbitrage humain — et reste intact. À l'intérieur d'un groupe
     * résolu, chaque fusion individuelle peut elle-même refuser de toucher
     * aux fiches si leurs dossiers de scolarité se chevauchent sur une même
     * année (cf. EleveFusionService) : impossible alors de savoir si un
     * paiement a été ressaisi en double ou s'il s'agit de deux versements
     * distincts, donc rien n'est automatiquement décidé à sa place.
     */
    public function traitementAutomatiqueDoublons(): JsonResponse
    {
        $eleves = Eleve::forSchool(Tenant::schoolIds())->whereNotNull('date_naissance')->get();

        $groupes = $eleves->groupBy(
            fn(Eleve $eleve) => $eleve->school_id . ':' . $this->nomDoublon($eleve->nom_complet) . ':' . $eleve->date_naissance->toDateString()
        );

        $fusionnes = 0;
        $conflits = [];
        $ambigus = [];

        foreach ($groupes as $groupe) {
            if ($groupe->count() < 2) continue;

            $avecClasse = $groupe->filter(fn(Eleve $eleve) => $eleve->classe_id !== null);
            if ($avecClasse->count() !== 1) {
                $ambigus[] = ['nom' => $groupe->first()->nom_complet, 'ids' => $groupe->pluck('id')->values()->all()];
                continue;
            }

            $conservee = $avecClasse->first();
            foreach ($groupe as $autre) {
                if ($autre->id === $conservee->id) continue;

                $resultat = $this->fusion->fusionner($conservee, $autre);
                if ($resultat['fusionne']) {
                    $fusionnes++;
                } else {
                    $conflits[] = [
                        'nom' => $conservee->nom_complet,
                        'conservee_id' => $conservee->id,
                        'autre_id' => $autre->id,
                        'raison' => $resultat['raison'],
                    ];
                }
            }
        }

        return ApiResponse::success(
            ['fusionnes' => $fusionnes, 'conflits' => $conflits, 'ambigus' => $ambigus],
            "{$fusionnes} doublon(s) fusionné(s) automatiquement, " . count($conflits) . ' en conflit financier, ' . count($ambigus) . ' ambigu(s) à traiter à la main.',
        );
    }

    /**
     * Détail des groupes de doublons, pour arbitrage humain : ni « avec
     * classe » ni « avec versements » ne départage la plupart des groupes en
     * pratique (souvent les deux exemplaires sont actifs dans la même classe,
     * ou tous deux inactifs) — les deux signaux sont donc remontés côte à
     * côte, sans décider à la place de l'établissement.
     *
     * Trié par urgence : un groupe où **plusieurs** exemplaires ont déjà reçu
     * des versements réels passe en premier — c'est là que le risque d'avoir
     * compté un paiement deux fois est le plus concret, cf. la remarque de
     * l'établissement qui a motivé cet écran plutôt qu'une fusion aveugle.
     */
    public function doublons(): JsonResponse
    {
        $eleves = Eleve::forSchool(Tenant::schoolIds())
            ->whereNotNull('date_naissance')
            ->with(['classe:id,nom', 'school:id,name', 'tuteurs:id,nom_complet,telephone'])
            ->get(['id', 'school_id', 'classe_id', 'nom_complet', 'date_naissance', 'matricule', 'statut', 'created_at']);

        $totaux = $this->totauxVersements($eleves->pluck('id'));

        $groupes = $eleves
            ->groupBy(fn(Eleve $eleve) => $eleve->school_id . ':' . $this->nomDoublon($eleve->nom_complet) . ':' . $eleve->date_naissance->toDateString())
            ->filter(fn($groupe) => $groupe->count() > 1)
            ->map(function ($groupe) use ($totaux) {
                $membres = $groupe->map(function (Eleve $eleve) use ($totaux) {
                    $principal = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

                    return [
                        'id' => $eleve->id,
                        'matricule' => $eleve->matricule,
                        'statut' => $eleve->statut,
                        'classe' => $eleve->classe?->nom,
                        'total_versements' => $totaux[$eleve->id] ?? 0,
                        'created_at' => $eleve->created_at?->format('Y-m-d H:i'),
                        'tuteur' => $principal ? ['nom_complet' => $principal->nom_complet, 'telephone' => $principal->telephone] : null,
                    ];
                })->values();

                $avecVersement = $membres->filter(fn($m) => $m['total_versements'] > 0)->count();
                $avecClasse = $membres->filter(fn($m) => $m['classe'] !== null)->count();

                return [
                    'nom' => $groupe->first()->nom_complet,
                    'date_naissance' => $groupe->first()->date_naissance->format('Y-m-d'),
                    'ecole' => $groupe->first()->school?->name,
                    // 3 : plusieurs exemplaires déjà payés — le cas le plus
                    // sensible. 2 : plusieurs actifs dans une classe, sans
                    // doublon d'argent connu. 1 : le reste (souvent inactifs).
                    'urgence' => match (true) {
                        $avecVersement >= 2 => 3,
                        $avecClasse >= 2 => 2,
                        default => 1,
                    },
                    'membres' => $membres,
                ];
            })
            ->sortByDesc('urgence')
            ->values();

        return ApiResponse::success($groupes, "{$groupes->count()} groupe(s) de doublons.");
    }

    /**
     * Fusion d'une paire choisie à la main : {@see EleveFusionService} refuse
     * elle-même si les deux fiches ont un dossier de scolarité sur une même
     * année (risque de double-compte d'un paiement), sans qu'il soit besoin
     * de le revérifier ici.
     */
    public function fusionnerDoublon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conservee_id' => ['required', 'integer', 'different:autre_id'],
            'autre_id' => ['required', 'integer'],
        ]);

        $conservee = Eleve::forSchool(Tenant::schoolIds())->findOrFail($data['conservee_id']);
        $autre = Eleve::forSchool(Tenant::schoolIds())->findOrFail($data['autre_id']);

        $resultat = $this->fusion->fusionner($conservee, $autre);

        return ApiResponse::success(
            $resultat,
            $resultat['fusionne']
                ? "{$autre->nom_complet} fusionné(e) dans la fiche conservée."
                : "Fusion refusée : dossiers de scolarité en chevauchement sur une même année. À traiter manuellement.",
        );
    }

    /**
     * Recherche transverse d'élèves, tous critères confondus : nom de
     * l'élève, matricule, ou nom/téléphone d'un de ses tuteurs. Pensée pour
     * une barre de recherche rapide, pas pour remplacer la liste filtrée.
     */
    public function rechercheGlobale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $eleves = $this->service->rechercheGlobale($request->user(), Tenant::schoolIds(), $data['q'], $request->boolean('tous'));

        $this->marquerNonReinscrits($eleves);
        $eleves->each(fn(Eleve $eleve) => $eleve->setAttribute(
            'preinscription_active',
            $this->preinscriptions->estPreinscritAnneeActive($eleve),
        ));

        return ApiResponse::success(EleveResource::collection($eleves));
    }

    private function marquerNonReinscrits(\Illuminate\Support\Collection $eleves): void
    {
        $ids = $this->preinscriptions->listeAnciensNonReinscrits(Tenant::schoolIds())->pluck('id')->all();
        $idsNonReinscrits = array_fill_keys($ids, true);

        $eleves->each(fn(Eleve $eleve) => $eleve->setAttribute(
            'non_reinscrit_annee_active',
            isset($idsNonReinscrits[$eleve->id]),
        ));
    }

    private function marquerTotalVersements(\Illuminate\Support\Collection $eleves): void
    {
        $totaux = $this->totauxVersements($eleves->pluck('id'));
        $eleves->each(fn(Eleve $eleve) => $eleve->setAttribute('total_versements', $totaux[$eleve->id] ?? 0));
    }

    private function totauxVersements(\Illuminate\Support\Collection $eleveIds): \Illuminate\Support\Collection
    {
        if ($eleveIds->isEmpty()) return collect();

        return DossierScolarite::whereIn('eleve_id', $eleveIds)
            ->withSum(['versements as total_versements' => fn($query) => $query->valides()], 'montant')
            ->get()->groupBy('eleve_id')
            ->map(fn($dossiers) => (int) $dossiers->sum('total_versements'));
    }

    private function nomDoublon(string $nom): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/', ' ', $nom))));
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

        if (! empty($data['matricule_national']) && ! School::findOrFail($schoolId)->estSecondaire()) {
            return ApiResponse::error('Le matricule national est réservé aux élèves du secondaire.', 422);
        }

        $eleve = $this->service->create($schoolId, $data);

        ActivityLog::enregistrer($request->user(), 'eleve.cree', "Inscription de {$eleve->nom_complet}.", $eleve);

        return ApiResponse::created(new EleveResource($eleve), 'Élève inscrit.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id, $request->user());

        return ApiResponse::success(new EleveResource($eleve));
    }

    /** Parcours scolaire de l'élève, année par année — cf. HistoriqueScolariteEleve, alimenté à chaque conseil de classe validé. */
    public function parcours(Request $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id, $request->user());

        $historique = HistoriqueScolariteEleve::where('eleve_id', $eleve->id)
            ->with('anneeScolaire')
            ->orderByDesc('annee_scolaire_id')
            ->get()
            ->map(fn(HistoriqueScolariteEleve $h) => [
                'annee_scolaire' => ['id' => $h->annee_scolaire_id, 'libelle' => $h->anneeScolaire->libelle],
                'classe_nom' => $h->classe_nom,
                'niveau_libelle' => $h->niveau_libelle,
                'moyenne_annuelle' => $h->moyenne_annuelle,
                'rang_annuel' => $h->rang_annuel,
                'decision' => $h->decision,
                'gracie' => $h->gracie,
                'motif' => $h->motif,
            ]);

        return ApiResponse::success($historique);
    }

    public function update(UpdateEleveRequest $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id, $request->user());
        $data = $request->validated();

        if (array_key_exists('matricule_national', $data) && $data['matricule_national'] !== null && ! $eleve->school->estSecondaire()) {
            return ApiResponse::error('Le matricule national est réservé aux élèves du secondaire.', 422);
        }

        $eleve = $this->service->update($eleve, $data);

        return ApiResponse::success(new EleveResource($eleve), 'Élève mis à jour.');
    }

    public function repartition(): JsonResponse
    {
        return ApiResponse::success($this->service->repartition(Tenant::schoolIds()));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $classeId = $request->integer('classe_id') ?: null;
        $sansClasse = $request->boolean('sans_classe');
        $nomFichier = $sansClasse ? 'eleves-sans-classe.xlsx' : 'eleves.xlsx';

        return Excel::download(new EleveExport(Tenant::schoolIds(), $classeId, $sansClasse), $nomFichier);
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
                $schoolId,
                $token,
                $data['index'],
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([...$result, 'dernier' => $dernier]);
    }

    public function photo(Request $request, int $id): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $eleve = $this->service->find(Tenant::schoolIds(), $id, $request->user());
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

        $eleve = $this->service->find(Tenant::schoolIds(), $id, $user);
        $eleve = $this->service->transferer($eleve, $classe);

        return ApiResponse::success(
            new EleveResource($eleve),
            'Élève transféré vers ' . $classe->school->name . ' — ' . $classe->nom . '.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id, $request->user());
        $this->service->delete($eleve);

        return ApiResponse::success(null, 'Élève supprimé.');
    }

    /**
     * Aperçu des fiches sans classe, jamais préinscrites et sans la moindre
     * trace d'activité (cf. Eleve::scopeNonPreinscritSansHistorique) — les
     * doublons laissés par un import massif mal dédupliqué. À afficher avant
     * `supprimerNonPreinscritsSansHistorique()`, jamais supprimé à l'aveugle.
     */
    public function nonPreinscritsSansHistorique(): JsonResponse
    {
        $eleves = $this->service->candidatsNonPreinscritsSansHistorique(Tenant::schoolIds());
        $eleves->load('school:id,name,code,type');

        return ApiResponse::success(EleveResource::collection($eleves));
    }

    /** Supprime le lot précédemment prévisualisé — recalculé côté serveur, cf. EleveService::supprimerNonPreinscritsSansHistorique(). */
    public function supprimerNonPreinscritsSansHistorique(): JsonResponse
    {
        $supprimes = $this->service->supprimerNonPreinscritsSansHistorique(Tenant::schoolIds());

        return ApiResponse::success(['deleted' => $supprimes], "{$supprimes} fiche(s) non préinscrite(s) supprimée(s).");
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
            $eleve = $this->service->find($schoolId, $id, $request->user());
            $this->service->delete($eleve);
            $deleted++;
        }

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} élève(s) supprimé(s).");
    }

    public function normaliserMatricules(): JsonResponse
    {
        $total = $this->service->normaliserMatricules(Tenant::schoolIds());

        return ApiResponse::success(['normalises' => $total], "{$total} matricule(s) normalisé(s).");
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
            $eleve = $this->service->find($schoolId, $id, $request->user());
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
            $eleve = $this->service->find($schoolId, $id, $user);
            $this->service->transferer($eleve, $classe);
            $transferes++;
        }

        return ApiResponse::success(['transferes' => $transferes], "{$transferes} élève(s) transféré(s) vers {$classe->school->name} — {$classe->nom}.");
    }

    /** Ouvre l'accès élève (portail lecture seule) — pendant de {@see TuteurController::creerCompteParent()}. */
    public function creerCompteEleve(Request $request, int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->findOrFail($id);

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
    public function basculerAcces(Request $request, int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->with('user')->findOrFail($id);

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
    public function supprimerCompteEleve(Request $request, int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->with('user')->findOrFail($id);

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
            $documents = $schools->map(fn(School $school) => [
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