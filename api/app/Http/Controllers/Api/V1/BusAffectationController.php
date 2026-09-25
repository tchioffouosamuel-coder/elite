<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\BusSouscriptionExport;
use App\Exports\ListePersonnaliseeExport;
use App\Exports\ModeleGenerique;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Imports\BusSouscriptionImport;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\ListePersonnaliseeModele;
use App\Models\School;
use App\Services\BusPaiementService;
use App\Services\BusService;
use App\Services\ListePersonnaliseeDocumentService;
use App\Services\PreinscriptionService;
use App\Support\ListeTransportColonnes;
use App\Support\Pdf\ListePersonnaliseeBusGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BusAffectationController extends Controller
{
    private const DOMAINE_LISTE = 'transport';

    public function __construct(
        private readonly BusService $service,
        private readonly BusPaiementService $paiements,
        private readonly PreinscriptionService $preinscriptions,
        private readonly ListePersonnaliseeDocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $trajetId = $request->integer('trajet_id') ?: null;

        $affectations = $this->service->listerAffectations(Tenant::schoolIds(), $trajetId);

        return ApiResponse::success($affectations->map(fn(BusAffectation $a) => $this->resumer($a))->values());
    }

    /** Tous les élèves de l'école, souscription bus incluse si elle existe — filtrable par classe. */
    public function eleves(Request $request): JsonResponse
    {
        $eleves = $this->service->listerElevesTransport(
            Tenant::schoolIds(),
            $request->integer('classe_id') ?: null,
            $request->integer('annee_scolaire_id') ?: null,
            $request->integer('eleve_id') ?: null,
        );

        return ApiResponse::success($eleves->map(fn(Eleve $e) => $this->resumerEleve($e))->values());
    }

    /**
     * Transport d'un seul élève, pour sa fiche — `null` s'il n'est pas
     * souscrit. Borné au périmètre du compte : un enseignant n'y lit que
     * les élèves de ses classes.
     */
    public function eleve(Request $request, int $eleveId): JsonResponse
    {
        Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->findOrFail($eleveId);

        $eleve = $this->service->listerElevesTransport(Tenant::schoolIds(), null, null, $eleveId)
            ->first(fn(Eleve $e) => $e->id === $eleveId);

        return ApiResponse::success($eleve ? $this->resumerEleve($eleve) : null);
    }

    /** Effectif souscrit par école et situation financière du mois en cours — les tuiles au-dessus de la liste. */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->statistiques(Tenant::schoolIds()));
    }

    /**
     * Liste personnalisée : les affectations filtrées (classe, trajet,
     * destination, sens, nom) et éventuellement regroupées, pour composer à
     * la volée le manifeste dont on a besoin (une classe entière, un
     * quartier, une fratrie…) sans passer par un trajet ou un véhicule précis.
     */
    public function listePersonnalisee(Request $request): JsonResponse
    {
        $filtres = $this->filtresListePersonnalisee($request);
        $groupePar = $this->groupePar($request);

        $affectations = $this->service->listerPourImpression(Tenant::schoolIds(), $filtres);
        $resumes = $affectations->map(fn(BusAffectation $a) => $this->resumer($a))->values();

        $resultats = $groupePar
            ? $resumes->groupBy(fn(array $r) => $this->cleGroupe($r, $groupePar))->map->values()
            : $resumes;

        return ApiResponse::success([
            'group_by' => $groupePar,
            'total' => $affectations->count(),
            'resultats' => $resultats,
        ]);
    }

    public function modelesListePersonnalisee(Request $request): JsonResponse
    {
        $modeles = ListePersonnaliseeModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->where('domaine', self::DOMAINE_LISTE)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success($modeles);
    }

    public function storeModeleListePersonnalisee(Request $request): JsonResponse
    {
        $modele = ListePersonnaliseeModele::create([
            ...$this->validerModeleListePersonnalisee($request),
            'domaine' => self::DOMAINE_LISTE,
            'school_id' => Tenant::schoolId(),
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::created($modele, 'Modèle enregistré.');
    }

    public function updateModeleListePersonnalisee(Request $request, int $id): JsonResponse
    {
        $modele = $this->modeleListePersonnalisee($request, $id);
        $modele->update($this->validerModeleListePersonnalisee($request));

        return ApiResponse::success($modele, 'Modèle mis à jour.');
    }

    public function destroyModeleListePersonnalisee(Request $request, int $id): JsonResponse
    {
        $this->modeleListePersonnalisee($request, $id)->delete();

        return ApiResponse::success(null, 'Modèle supprimé.');
    }

    /** Même filtrage que `listePersonnalisee`, restitué en PDF imprimable. */
    public function listePersonnaliseePdf(Request $request): Response
    {
        if ($request->has('colonnes')) {
            [$ecole, $titreFr, $titreEn, $colonnes, $lignes, $meta] = $this->preparerListePersonnaliseeDocument($request);

            $pdf = $this->documents->genererPdf($ecole, $titreFr, $titreEn, $colonnes, $lignes, ListeTransportColonnes::DEFINITIONS, $meta);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="liste-personnalisee-transport.pdf"',
            ]);
        }

        $filtres = $this->filtresListePersonnalisee($request);
        $groupePar = $this->groupePar($request);

        $affectations = $this->service->listerPourImpression(Tenant::schoolIds(), $filtres);
        $ecole = School::findOrFail(Tenant::schoolId());

        $pdf = (new ListePersonnaliseeBusGenerator)->build(
            $affectations,
            $groupePar,
            $this->filtresLabel($filtres),
            $ecole,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-personnalisee-bus.pdf"',
        ]);
    }

    public function listePersonnaliseeWord(Request $request): BinaryFileResponse
    {
        [$ecole, $titreFr, $titreEn, $colonnes, $lignes, $meta] = $this->preparerListePersonnaliseeDocument($request);
        $path = $this->documents->genererWord($ecole, $titreFr, $titreEn, $colonnes, $lignes, ListeTransportColonnes::DEFINITIONS, $meta);

        return response()->download($path, 'liste-personnalisee-transport.docx')->deleteFileAfterSend();
    }

    public function listePersonnaliseeExcel(Request $request): BinaryFileResponse
    {
        [, $titreFr, , $colonnes, $lignes] = $this->preparerListePersonnaliseeDocument($request);

        return Excel::download(
            new ListePersonnaliseeExport($colonnes, $lignes, $this->entetesListeTransport(), $titreFr),
            'liste-personnalisee-transport.xlsx',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->validerSouscription($request);
        // L'école n'est jamais à choisir ici : c'est celle de l'élève qu'on
        // affecte, même pour un compte multi-écoles en mode agrégé.
        $schoolId = Eleve::whereKey($donnees['eleve_id'])->value('school_id');

        try {
            $affectation = $this->service->affecterEleve($schoolId, $donnees);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resumer($affectation), 'Élève souscrit au bus.');
    }

    /** Souscrit plusieurs élèves d'un coup au même trajet (fratrie, classe entière…). */
    public function souscrireLot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eleve_ids' => ['required', 'array', 'min:1'],
            'eleve_ids.*' => ['integer', Rule::exists('eleves', 'id')->whereIn('school_id', Tenant::schoolIds())],
        ]);

        $donnees = $this->validerSouscription($request);
        unset($donnees['eleve_id']);

        $resultat = $this->service->souscrireEnLot($data['eleve_ids'], $donnees);

        $message = "{$resultat['souscrits']} élève(s) souscrit(s) au bus.";
        if ($resultat['ignores'] !== []) {
            $message .= ' Déjà affecté(s) : ' . implode(', ', $resultat['ignores']) . '.';
        }

        return ApiResponse::success($resultat, $message);
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new BusSouscriptionExport(Tenant::schoolIds(), $this->service), 'souscriptions-bus.xlsx');
    }

    public function modele(): BinaryFileResponse
    {
        return Excel::download(new ModeleGenerique(BusSouscriptionImport::enTetes()), 'modele-souscriptions-bus.xlsx');
    }

    /** Import massif d'une situation de transport (souscriptions + versements) — cf. `BusSouscriptionImport`. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = Tenant::schoolId();
        $import = new BusSouscriptionImport($schoolId, $this->service, $this->paiements, $request->user()->id);
        Excel::import($import, $request->file('file'));

        $message = "{$import->versementsCrees} versement(s) importé(s).";
        if ($import->versementsIgnores > 0) {
            $message .= " {$import->versementsIgnores} déjà présent(s), ignoré(s).";
        }
        if (count($import->erreurs) > 0) {
            $message .= ' ' . count($import->erreurs) . ' ligne(s) en erreur.';
        }

        return ApiResponse::success([
            'imported' => $import->versementsCrees,
            'ignored' => $import->versementsIgnores,
            'failed' => count($import->erreurs),
            'erreurs' => $import->erreurs,
        ], $message);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $affectation = $this->affectation($id);

        $donnees = $request->validate([
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $affectation->trajet_id)],
            'arret_nom' => ['nullable', 'string', 'max:150'],
            'statut' => ['nullable', 'in:actif,suspendu'],
            'option_trajet' => ['nullable', Rule::in(BusAffectation::OPTIONS_TRAJET)],
            'remise' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $affectation = $this->service->modifierAffectation($affectation, $donnees);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resumer($affectation), 'Affectation mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->retirerAffectation($this->affectation($id));

        return ApiResponse::success(null, 'Affectation retirée.');
    }

    /** Retrait en lot — même règle que l'unitaire : suspendue si des versements existent, supprimée sinon (cf. BusService::retirerAffectation). */
    public function batchDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $retirees = 0;
        foreach ($data['ids'] as $id) {
            $this->service->retirerAffectation($this->affectation($id));
            $retirees++;
        }

        return ApiResponse::success(['retirees' => $retirees], "{$retirees} souscription(s) retirée(s).");
    }

    /**
     * Règles communes à la souscription individuelle et en lot — le tarif ne
     * s'y trouve jamais : il vient de l'arrêt, jamais d'une saisie.
     */
    private function validerSouscription(Request $request): array
    {
        return $request->validate([
            // Scopé aux écoles accessibles (et non à l'école ambiante du
            // tenant) : en mode agrégé, l'élève visé peut appartenir à
            // n'importe laquelle d'entre elles, pas seulement à celle retenue
            // par défaut pour le compte.
            'eleve_id' => ['required_without:eleve_ids', 'integer', Rule::exists('eleves', 'id')->whereIn('school_id', Tenant::schoolIds())],
            // Un trajet dessert souvent plusieurs écoles du même complexe sur
            // le même circuit : pas de filtre d'école ici, cf. BusTrajet::scopeForSchool.
            'trajet_id' => ['required', 'integer', Rule::exists('bus_trajets', 'id')],
            // Un arrêt n'appartenant pas au trajet choisi n'a pas de sens :
            // le champ « ramassera » un enfant sur un circuit qu'il ne suit pas.
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $request->integer('trajet_id'))],
            'arret_nom' => ['nullable', 'string', 'max:150'],
            'annee_scolaire_id' => ['nullable', 'integer', Rule::exists('annee_scolaires', 'id')->whereIn('school_id', Tenant::schoolIds())],
            'option_trajet' => ['required', Rule::in(BusAffectation::OPTIONS_TRAJET)],
            // Remise mensuelle accordée à la souscription, déduite du tarif de
            // l'arrêt chaque mois (cf. BusAffectation::tarif_net).
            'remise' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(BusAffectation $affectation): array
    {
        $affectation->loadMissing(['eleve.classe', 'eleve.school', 'trajet', 'arret']);

        return [
            'id' => $affectation->id,
            'statut' => $affectation->statut,
            'tarif_mensuel' => $affectation->tarif_mensuel,
            'remise' => $affectation->remise,
            'tarif_net' => $affectation->tarif_net,
            'statut_paiement' => $affectation->statut_paiement,
            'option_trajet' => $affectation->option_trajet,
            'eleve' => [
                'id' => $affectation->eleve->id,
                'nom_complet' => $affectation->eleve->nom_complet,
                'matricule' => $affectation->eleve->matricule,
                'classe' => $affectation->eleve->classe?->nom,
            ],
            'trajet' => ['id' => $affectation->trajet->id, 'nom' => $affectation->trajet->nom],
            'arret' => $affectation->arret ? [
                'id' => $affectation->arret->id,
                'nom' => $affectation->arret->nom,
                'lieu_dit' => $affectation->arret->lieu_dit,
                'heure_passage' => $affectation->arret->heure_passage,
            ] : null,
            // L'école de l'élève, pas celle du trajet : voir BusPaiementService::encaisser().
            'school' => $affectation->eleve->school ? [
                'id' => $affectation->eleve->school->id,
                'name' => $affectation->eleve->school->name,
                'code' => $affectation->eleve->school->code,
                'type' => $affectation->eleve->school->type,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function resumerEleve(Eleve $eleve): array
    {
        $affectation = $eleve->busAffectations->first();

        return [
            'id' => $eleve->id,
            'nom_complet' => $eleve->nom_complet,
            'matricule' => $eleve->matricule,
            'classe' => $eleve->classe ? ['id' => $eleve->classe->id, 'nom' => $eleve->classe->nom] : null,
            'school' => $eleve->school ? [
                'id' => $eleve->school->id,
                'name' => $eleve->school->name,
                'code' => $eleve->school->code,
                'type' => $eleve->school->type,
            ] : null,
            'bus' => $affectation?->trajet ? [
                'affectation_id' => $affectation->id,
                'trajet' => ['id' => $affectation->trajet->id, 'nom' => $affectation->trajet->nom],
                'arret' => $affectation->arret ? [
                    'id' => $affectation->arret->id,
                    'nom' => $affectation->arret->nom,
                    'lieu_dit' => $affectation->arret->lieu_dit,
                    'heure_passage' => $affectation->arret->heure_passage,
                ] : null,
                'option_trajet' => $affectation->option_trajet,
                'tarif_mensuel' => $affectation->tarif_mensuel,
                'remise' => $affectation->remise,
                'tarif_net' => $affectation->tarif_net,
                'statut_paiement' => $affectation->statut_paiement,
            ] : null,
            'moratoire' => $eleve->moratoire_valide ? [
                'date_expiration' => $eleve->moratoire_valide->date_expiration->format('Y-m-d'),
                'jours_restants' => (int) Carbon::today()->diffInDays($eleve->moratoire_valide->date_expiration, false),
            ] : null,
            // Pour distinguer, dans cette liste volontairement large (cf.
            // `listerElevesTransport`, sans filtre de préinscription), les
            // vrais élèves des doublons/fiches vides laissés par un import
            // massif — jamais engagés pour l'année active.
            'preinscrit_annee_active' => $this->preinscriptions->estPreinscritAnneeActive($eleve),
        ];
    }

    private const GROUPES_VALIDES = ['classe', 'trajet', 'arret', 'option_trajet'];

    /** @return array{classe_id?: int, trajet_id?: int, arret_id?: int, option_trajet?: string, statut?: string, nom?: string} */
    private function filtresListePersonnalisee(Request $request): array
    {
        return array_filter([
            'classe_id' => $request->integer('classe_id') ?: null,
            'trajet_id' => $request->integer('trajet_id') ?: null,
            'arret_id' => $request->integer('arret_id') ?: null,
            'option_trajet' => $request->string('option_trajet')->toString() ?: null,
            'statut' => $request->string('statut')->toString() ?: null,
            'nom' => $request->string('nom')->toString() ?: null,
        ], fn($v) => $v !== null);
    }

    private function groupePar(Request $request): ?string
    {
        $groupe = $request->string('group_by')->toString() ?: null;

        return in_array($groupe, self::GROUPES_VALIDES, true) ? $groupe : null;
    }

    /** @param array<string, mixed> $resume */
    private function cleGroupe(array $resume, string $groupePar): string
    {
        return match ($groupePar) {
            'classe' => $resume['eleve']['classe'] ?: 'Sans classe',
            'trajet' => $resume['trajet']['nom'] ?: 'Sans trajet',
            'arret' => $resume['arret']['nom'] ?? 'Sans arrêt',
            'option_trajet' => $resume['option_trajet'],
            default => '—',
        };
    }

    private const LIBELLES_OPTION_TRAJET = [
        'aller_simple' => 'Aller simple',
        'retour_simple' => 'Retour simple',
        'aller_retour' => 'Aller-retour',
    ];

    private const LIBELLES_STATUT_PAIEMENT = [
        'sans_frais' => 'Sans frais',
        'impaye' => 'Impayé',
        'partiel' => 'Partiel',
        'solde' => 'Soldé',
    ];

    /** Libellés lisibles des filtres actifs, pour le bandeau du PDF. */
    private function filtresLabel(array $filtres): array
    {
        $labels = [];

        if (isset($filtres['classe_id'])) {
            $labels['Classe'] = Classe::find($filtres['classe_id'])?->nom ?? (string) $filtres['classe_id'];
        }
        if (isset($filtres['trajet_id'])) {
            $labels['Trajet'] = BusTrajet::find($filtres['trajet_id'])?->nom ?? (string) $filtres['trajet_id'];
        }
        if (isset($filtres['arret_id'])) {
            $labels['Arrêt'] = BusArret::find($filtres['arret_id'])?->nom ?? (string) $filtres['arret_id'];
        }
        if (isset($filtres['option_trajet'])) {
            $labels['Sens'] = self::LIBELLES_OPTION_TRAJET[$filtres['option_trajet']] ?? $filtres['option_trajet'];
        }
        if (isset($filtres['nom'])) {
            $labels['Nom'] = $filtres['nom'];
        }
        if (isset($filtres['statut'])) {
            $labels['Statut'] = $filtres['statut'];
        }

        return $labels;
    }

    /**
     * @return array{0: School, 1: string, 2: string, 3: list<string>, 4: list<array<string, string>>, 5: array<string, string>}
     */
    private function preparerListePersonnaliseeDocument(Request $request): array
    {
        $data = $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'string'],
            'classe_id' => ['nullable', 'integer'],
            'trajet_id' => ['nullable', 'integer'],
            'arret_id' => ['nullable', 'integer'],
            'option_trajet' => ['nullable', Rule::in(BusAffectation::OPTIONS_TRAJET)],
            'statut' => ['nullable', Rule::in(['actif', 'suspendu'])],
            'nom' => ['nullable', 'string', 'max:120'],
        ]);

        $colonnes = $this->colonnesListeTransportValidees($data['colonnes']);
        $filtres = $this->filtresListePersonnalisee($request);
        $affectations = $this->service->listerPourImpression(Tenant::schoolIds(), $filtres);
        $lignes = $this->lignesListeTransport($affectations, $colonnes);
        $ecole = School::whereIn('id', Tenant::schoolIds())->orderBy('name')->firstOrFail();
        $meta = $this->filtresLabel($filtres);

        if (Tenant::isAggregate()) {
            $meta = ['Écoles / Schools' => (string) count(Tenant::schoolIds()), ...$meta];
        } else {
            $meta = ['École / School' => $ecole->name, ...$meta];
        }

        return [$ecole, $data['titre_fr'], $data['titre_en'], $colonnes, $lignes, $meta];
    }

    /** @param \Illuminate\Support\Collection<int, BusAffectation> $affectations */
    private function lignesListeTransport($affectations, array $colonnes): array
    {
        return $affectations->sortBy(fn(BusAffectation $a) => $a->eleve?->nom_complet)
            ->values()
            ->map(function (BusAffectation $affectation, int $index) use ($colonnes) {
                $ligne = [];
                foreach ($colonnes as $colonne) {
                    $ligne[$colonne] = $this->valeurColonneTransport($colonne, $affectation, $index + 1);
                }

                return $ligne;
            })
            ->all();
    }

    private function valeurColonneTransport(string $colonne, BusAffectation $affectation, int $rang): string
    {
        $eleve = $affectation->eleve;

        return match ($colonne) {
            'numero' => (string) $rang,
            'nom_prenom' => $eleve?->nom_complet ?: '—',
            'matricule' => $eleve?->matricule ?: '—',
            'classe' => $eleve?->classe?->nom ?: '—',
            'trajet' => $affectation->trajet?->nom ?: '—',
            'arret' => $affectation->arret?->nom ?: '—',
            'lieu_dit' => $affectation->arret?->lieu_dit ?: '—',
            'heure_passage' => $affectation->arret?->heure_passage ?: '—',
            'option_trajet' => self::LIBELLES_OPTION_TRAJET[$affectation->option_trajet] ?? $affectation->option_trajet,
            'tarif_mensuel' => $affectation->tarif_mensuel === null ? '—' : number_format($affectation->tarif_mensuel, 0, ',', ' '),
            'statut_paiement' => self::LIBELLES_STATUT_PAIEMENT[$affectation->statut_paiement] ?? $affectation->statut_paiement,
            'statut' => $affectation->statut === 'actif' ? 'Actif' : 'Suspendu',
            'ecole' => $eleve?->school?->name ?: '—',
            default => '—',
        };
    }

    /** @return list<string> */
    private function colonnesListeTransportValidees(string $colonnesBrutes): array
    {
        $colonnes = array_values(array_filter(array_map('trim', explode(',', $colonnesBrutes))));

        abort_if($colonnes === [], 422, 'Au moins une colonne doit être choisie.');
        abort_if(array_diff($colonnes, ListeTransportColonnes::clesValides()) !== [], 422, 'Colonne inconnue.');

        return $colonnes;
    }

    private function validerModeleListePersonnalisee(Request $request): array
    {
        return $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'array', 'min:1'],
            'colonnes.*' => [Rule::in(ListeTransportColonnes::clesValides())],
        ]);
    }

    private function modeleListePersonnalisee(Request $request, int $id): ListePersonnaliseeModele
    {
        return ListePersonnaliseeModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->where('domaine', self::DOMAINE_LISTE)
            ->findOrFail($id);
    }

    /** @return array<string, string> */
    private function entetesListeTransport(): array
    {
        return collect(ListeTransportColonnes::clesValides())
            ->mapWithKeys(fn(string $cle) => [$cle => ListeTransportColonnes::libelle($cle)])
            ->all();
    }

    private function affectation(int $id): BusAffectation
    {
        // Par l'élève, pas par le trajet : cf. BusService::listerAffectations().
        return BusAffectation::whereHas('eleve', fn($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($id);
    }
}
