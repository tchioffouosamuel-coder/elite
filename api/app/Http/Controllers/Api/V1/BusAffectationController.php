<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\BusSouscriptionExport;
use App\Exports\ModeleGenerique;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Imports\BusSouscriptionImport;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Services\BusPaiementService;
use App\Services\BusService;
use App\Services\PreinscriptionService;
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
    public function __construct(
        private readonly BusService $service,
        private readonly BusPaiementService $paiements,
        private readonly PreinscriptionService $preinscriptions,
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
        );

        return ApiResponse::success($eleves->map(fn(Eleve $e) => $this->resumerEleve($e))->values());
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

    /** Même filtrage que `listePersonnalisee`, restitué en PDF imprimable. */
    public function listePersonnaliseePdf(Request $request): Response
    {
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
        ]);

        $affectation = $this->service->modifierAffectation($affectation, $donnees);

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
     * s'y trouve jamais : il vient du trajet, jamais d'une saisie.
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
            'bus' => $affectation ? [
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

    private function affectation(int $id): BusAffectation
    {
        // Par l'élève, pas par le trajet : cf. BusService::listerAffectations().
        return BusAffectation::whereHas('eleve', fn($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($id);
    }
}
