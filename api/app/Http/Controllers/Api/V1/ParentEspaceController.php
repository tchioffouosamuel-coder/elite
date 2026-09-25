<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\BibliothequeDocument;
use App\Models\BulletinPublication;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\ModificationEleve;
use App\Models\Moratoire;
use App\Models\Observation;
use App\Models\Presence;
use App\Models\Sanction;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use App\Models\VisiteInfirmerie;
use App\Http\Resources\Api\V1\SanctionResource;
use App\Http\Resources\Api\V1\VisiteInfirmerieResource;
use App\Services\BulletinPrimaireService;
use App\Services\BulletinService;
use App\Services\EleveService;
use App\Services\EmploiDuTempsService;
use App\Services\JustificationAbsenceService;
use App\Services\BibliothequeService;
use App\Services\ModificationEleveService;
use App\Services\ObservationService;
use App\Services\DisciplineService;
use App\Services\ProgressionService;
use App\Services\EcheancierService;
use App\Services\ScolariteService;
use App\Support\ParentAccess;
use App\Support\Pdf\BulletinGenerator;
use App\Support\Pdf\BulletinPrimaireGenerator;
use App\Support\Pdf\EmploiDuTempsGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portail parent : chacune de ces actions vérifie explicitement que l'élève
 * visé est un enfant du compte connecté (cf. {@see ParentAccess}) — les
 * privilèges du rôle `parent` (`finance.view`, `notes.view`…) ne bornent que
 * l'école, pas la fiche précise.
 */
class ParentEspaceController extends Controller
{
    public function __construct(
        private readonly ScolariteService $scolarite,
        private readonly BulletinService $bulletins,
        private readonly BulletinPrimaireService $bulletinsPrimaire,
        private readonly ProgressionService $progression,
        private readonly DisciplineService $discipline,
        private readonly JustificationAbsenceService $justifications,
        private readonly ModificationEleveService $modifications,
        private readonly ObservationService $observations,
        private readonly EmploiDuTempsService $emploiDuTemps,
        private readonly EcheancierService $echeancier,
        private readonly BibliothequeService $bibliotheque,
        private readonly EmploiDuTempsGenerator $emploiDuTempsPdf,
        private readonly EleveService $eleves,
    ) {}

    /**
     * Champs de l'élève considérés comme obligatoires pour un dossier complet,
     * en dehors de l'identité (nom, classe) déjà garantie à l'inscription —
     * utilisé à la fois par l'alerte "informations manquantes" et par
     * `completerEnfant()` pour n'accepter que ce qui est réellement attendu.
     *
     * @var list<string>
     */
    private const CHAMPS_ELEVE = [
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'adresse',
        'numero_acte_naissance',
        'lieu_delivrance_acte',
        'officier_etat_civil',
        'groupe_sanguin',
        'situation_sanitaire',
        'allergies',
    ];

    /** @var list<string> */
    private const CHAMPS_TUTEUR = ['telephone', 'email', 'profession', 'lieu_service', 'adresse'];

    /**
     * Vue d'ensemble des informations manquantes sur le compte connecté :
     * la fiche tuteur du parent lui-même, et chacun de ses enfants. Sert à
     * déclencher l'alerte "informations manquantes" à l'ouverture du portail
     * (web comme mobile) sans avoir à recharger le dossier complet de
     * chaque enfant.
     */
    public function champsManquants(Request $request): JsonResponse
    {
        $enfants = ParentAccess::enfants($request->user());
        $tuteur = Tuteur::where('user_id', $request->user()->id)->first();

        $champsTuteur = $tuteur ? $this->champsManquantsTuteur($tuteur) : [];

        $champsEnfants = $enfants
            ->map(fn(Eleve $e) => [
                'id' => $e->id,
                'nom_complet' => $e->nom_complet,
                'champs' => $this->champsManquantsEleve($e),
            ])
            ->filter(fn(array $x) => $x['champs'] !== [])
            ->values();

        return ApiResponse::success([
            'tuteur' => $tuteur ? ['id' => $tuteur->id, 'champs' => $champsTuteur] : null,
            'enfants' => $champsEnfants,
            'total' => count($champsTuteur) + $champsEnfants->sum(fn(array $x) => count($x['champs'])),
        ]);
    }

    /** @return list<string> */
    private function champsManquantsEleve(Eleve $e): array
    {
        $champs = array_values(array_filter(self::CHAMPS_ELEVE, fn(string $champ) => blank($e->{$champ})));

        if (blank($e->photo_path)) {
            $champs[] = 'photo';
        }

        return $champs;
    }

    /** @return list<string> */
    private function champsManquantsTuteur(Tuteur $tuteur): array
    {
        return array_values(array_filter(self::CHAMPS_TUTEUR, function (string $champ) use ($tuteur) {
            if ($champ === 'telephone') {
                return $tuteur->telephones()->doesntExist() && blank($tuteur->telephone);
            }

            return blank($tuteur->{$champ});
        }));
    }

    /**
     * Documents de la bibliothèque numérique visibles pour les écoles des
     * enfants du compte connecté — un document restreint à une classe (cf.
     * `BibliothequeDocument::classes()`) n'apparaît que si l'un des enfants y
     * est inscrit ; sans classe rattachée, il reste visible par toute l'école.
     */
    public function bibliotheque(Request $request): JsonResponse
    {
        $enfants = ParentAccess::enfants($request->user());
        $ecoleIds = $enfants->pluck('school_id')->filter()->unique()->values()->all();

        if (empty($ecoleIds)) {
            return ApiResponse::success([]);
        }

        $classeIds = $enfants->pluck('classe_id')->filter()->unique()->values()->all();
        $documents = $this->bibliotheque->listerPourParent($ecoleIds, $classeIds);

        return ApiResponse::success($documents->map(fn(BibliothequeDocument $d) => [
            'id' => $d->id,
            'titre' => $d->titre,
            'description' => $d->description,
            'fichier_url' => $d->fichier_url,
            'apercu_url' => $d->apercu_url,
            'fichier_nom_original' => $d->fichier_nom_original,
            'taille' => $d->taille,
            'type_mime' => $d->type_mime,
            'created_at' => $d->created_at->format('Y-m-d H:i'),
        ])->values());
    }

    /** Enfants du compte connecté — la liste qui ouvre le portail. */
    public function mesEnfants(Request $request): JsonResponse
    {
        $enfants = ParentAccess::enfants($request->user());

        return ApiResponse::success($enfants->map(fn(Eleve $e) => [
            'id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nom_complet,
            'sexe' => $e->sexe,
            'photo_url' => $e->photo_path ? asset('storage/' . $e->photo_path) : null,
            'classe' => $e->classe ? ['id' => $e->classe->id, 'nom' => $e->classe->nom] : null,
            'school' => $e->school ? ['id' => $e->school->id, 'name' => $e->school->name] : null,
        ]));
    }

    /** Dossier consolidé d'un enfant : identité, acte de naissance, santé, tuteurs — tout sur un seul écran. */
    public function enfant(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        return ApiResponse::success($this->presenterEnfant($e));
    }

    private function presenterEnfant(Eleve $e): array
    {
        return [
            'id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nom_complet,
            'sexe' => $e->sexe,
            'date_naissance' => $e->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $e->lieu_naissance,
            'nationalite' => $e->nationalite,
            'adresse' => $e->adresse,
            'photo_url' => $e->photo_path ? asset('storage/' . $e->photo_path) : null,
            'photo_tenue_url' => $e->photo_tenue_path ? asset('storage/' . $e->photo_tenue_path) : null,
            'redoublant' => (bool) $e->redoublant,
            'statut' => $e->statut,
            'classe' => $e->classe ? ['id' => $e->classe->id, 'nom' => $e->classe->nom, 'sous_systeme' => $e->classe->sousSysteme?->nom] : null,
            'school' => $e->school ? ['id' => $e->school->id, 'name' => $e->school->name, 'type' => $e->school->type] : null,
            'acte_naissance' => [
                'numero' => $e->numero_acte_naissance,
                'lieu_delivrance' => $e->lieu_delivrance_acte,
                'officier_etat_civil' => $e->officier_etat_civil,
            ],
            'sante' => [
                'groupe_sanguin' => $e->groupe_sanguin,
                'situation_sanitaire' => $e->situation_sanitaire,
                'aptitude' => $e->aptitude,
                'allergies' => $e->allergies,
            ],
            'tuteurs' => $e->tuteurs->map(fn(Tuteur $t) => [
                'id' => $t->id,
                'nom_complet' => $t->nom_complet,
                'telephone' => $t->telephone,
                'telephones' => $t->telephones->map(fn($tel) => ['numero' => $tel->numero, 'is_principal' => (bool) $tel->is_principal])->values(),
                'email' => $t->email,
                'profession' => $t->profession,
                'lieu_service' => $t->lieu_service,
                'adresse' => $t->adresse,
                'lien_parente' => $t->pivot->lien_parente,
                'is_principal' => (bool) $t->pivot->is_principal,
            ]),
        ];
    }

    /**
     * Complète directement les champs de l'enfant encore vides — sans passer
     * par la validation admin de `soumettreModification()` : il ne s'agit
     * pas de corriger une donnée déjà renseignée, seulement de remplir un
     * blanc. Un champ déjà rempli côté base est donc ignoré même s'il est
     * envoyé, pour qu'on ne puisse pas se servir de cette voie pour écraser
     * une valeur existante sans passer par l'approbation de l'école.
     */
    public function completerEnfant(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $data = $request->validate([
            'sexe' => ['sometimes', 'in:M,F'],
            'date_naissance' => ['sometimes', 'date'],
            'lieu_naissance' => ['sometimes', 'string', 'max:255'],
            'adresse' => ['sometimes', 'string', 'max:255'],
            'numero_acte_naissance' => ['sometimes', 'string', 'max:100'],
            'lieu_delivrance_acte' => ['sometimes', 'string', 'max:255'],
            'officier_etat_civil' => ['sometimes', 'string', 'max:255'],
            'groupe_sanguin' => ['sometimes', 'string', 'max:10'],
            'situation_sanitaire' => ['sometimes', 'string', 'max:1000'],
            'allergies' => ['sometimes', 'string', 'max:1000'],
        ]);

        $aAppliquer = collect($data)
            ->only(self::CHAMPS_ELEVE)
            ->filter(fn($valeur, string $champ) => blank($e->{$champ}))
            ->all();

        if ($aAppliquer === []) {
            return ApiResponse::error('Ces informations sont déjà renseignées.', 422);
        }

        $e->fill($aAppliquer)->save();

        return ApiResponse::success($this->presenterEnfant($e->fresh(['classe.sousSysteme', 'school', 'tuteurs.telephones'])), 'Informations complétées.');
    }

    /**
     * Photo de l'enfant, quand aucune n'est encore enregistrée — même
     * traitement (recadrage carré 600×600) que côté personnel, cf.
     * `EleveController::photo()`. Refusé si une photo existe déjà : la
     * remplacer reste un geste réservé à l'établissement.
     */
    public function completerPhotoEnfant(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if ($e->photo_path) {
            return ApiResponse::error('Une photo est déjà enregistrée pour cet enfant.', 422);
        }

        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $e = $this->eleves->updatePhoto($e, $request->file('photo'));

        return ApiResponse::success(['photo_url' => asset('storage/' . $e->photo_path)], 'Photo ajoutée.');
    }

    /**
     * Complète directement les champs encore vides de la fiche tuteur du
     * compte connecté — même logique additive que `completerEnfant()`.
     */
    public function completerTuteur(Request $request): JsonResponse
    {
        $tuteur = $this->tuteurDe($request);

        $data = $request->validate([
            'telephone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255'],
            'profession' => ['sometimes', 'string', 'max:255'],
            'lieu_service' => ['sometimes', 'string', 'max:255'],
            'adresse' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($data['telephone']) && $tuteur->telephones()->doesntExist() && blank($tuteur->telephone)) {
            TuteurTelephone::create(['tuteur_id' => $tuteur->id, 'numero' => $data['telephone'], 'is_principal' => true]);
            $tuteur->telephone = $data['telephone'];
        }
        unset($data['telephone']);

        $aAppliquer = collect($data)
            ->only(['email', 'profession', 'lieu_service', 'adresse'])
            ->filter(fn($valeur, string $champ) => blank($tuteur->{$champ}))
            ->all();

        $tuteur->fill($aAppliquer)->save();

        return ApiResponse::success([
            'id' => $tuteur->id,
            'telephone' => $tuteur->telephone,
            'email' => $tuteur->email,
            'profession' => $tuteur->profession,
            'lieu_service' => $tuteur->lieu_service,
            'adresse' => $tuteur->adresse,
        ], 'Informations complétées.');
    }

    /** Situation financière de l'année active — mêmes chiffres que la caisse, vus par la famille. */
    public function finance(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);
        $annee = $this->anneeActive($e->school_id);

        if (! $annee) {
            return ApiResponse::success(null, "Aucune année scolaire active pour l'instant.");
        }

        $dossier = $this->scolarite->dossier($e, $annee);
        $dossier->loadMissing([
            'fraisAnnexes',
            'versements' => fn($q) => $q->valides()->with('lignes'),
            'busAffectations.trajet',
            'busAffectations.arret',
            'busAffectations.anneeScolaire',
            'busAffectations.versements',
        ]);
        $bus = $dossier->bus_actif;

        // Un moratoire valide est l'échéance qui concerne réellement cette
        // famille ; la date d'exclusion générale de l'école n'est affichée en
        // repli que si aucun moratoire ne couvre déjà l'enfant.
        $moratoire = Moratoire::where('eleve_id', $e->id)->valides()->latest('date_expiration')->first();

        return ApiResponse::success([
            'montant_scolarite' => $dossier->montant_scolarite,
            'remise' => $dossier->remise,
            'report_dette' => $dossier->report_dette,
            'total_du' => $dossier->total_du,
            'total_paye' => $dossier->total_paye,
            'reste_a_payer' => $dossier->reste_a_payer,
            'statut_paiement' => $dossier->statut_paiement,
            'rubriques' => $dossier->rubriques,
            // Échéancier de la scolarité : ce que la famille doit, quand, et ce
            // qui reste sur chaque tranche. `actif` à faux quand l'école n'a
            // pas découpé son année — l'écran affiche alors la seule date limite.
            'echeancier' => $this->echeancier->pourDossier($dossier),
            'versements' => $dossier->versements->whereNull('annule_le')->map(fn($v) => [
                'numero_recu' => $v->numero_recu,
                'date_versement' => $v->date_versement?->format('Y-m-d'),
                'montant' => $v->montant,
                'mode' => $v->mode,
            ])->values(),
            'date_limite_paiement' => Setting::get($e->school_id, 'date_limite_paiement') ?: null,
            'date_exclusion_insolvables' => Setting::get($e->school_id, 'date_exclusion_insolvables') ?: null,
            'moratoire' => $moratoire ? [
                'date_expiration' => $moratoire->date_expiration->format('Y-m-d'),
                'motif' => $moratoire->motif,
            ] : null,
            'bus' => $bus ? [
                'affectation_id' => $bus->id,
                'trajet' => $bus->trajet?->nom,
                'arret' => $bus->arret?->nom,
                'option_trajet' => $bus->option_trajet,
                'tarif_mensuel' => $bus->tarif_mensuel,
                'remise' => $bus->remise,
                'situation_mensuelle' => $bus->situation_mensuelle,
            ] : null,
        ]);
    }

    /** Emploi du temps de la classe de l'enfant — celui de la classe, pas propre à l'élève. */
    public function emploiDuTemps(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if (! $e->classe) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->emploiDuTemps->grille($e->classe)->map(EmploiDuTempsService::presenter(...)));
    }

    /** PDF de l'emploi du temps de la classe de l'enfant, borné par ParentAccess. */
    public function emploiDuTempsPdf(Request $request, int $eleveId)
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);
        abort_if(! $e->classe, 404, "Cet élève n'a pas de classe.");
        $annee = AnneeScolaire::where('school_id', $e->classe->school_id)->where('is_active', true)->firstOrFail();

        return response($this->emploiDuTempsPdf->build($e->classe, $annee, $this->emploiDuTemps->grille($e->classe)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="emploi-du-temps-' . $e->classe->id . '.pdf"',
        ]);
    }

    /** Visites à l'infirmerie de cet enfant, les plus récentes en tête. */
    public function visitesInfirmerie(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $visites = VisiteInfirmerie::where('eleve_id', $e->id)
            ->with(['eleve.school', 'classe', 'enregistrePar', 'malaises', 'materiels.article'])
            ->latest('date_visite')
            ->get();

        return ApiResponse::success(VisiteInfirmerieResource::collection($visites));
    }

    /**
     * Dossier disciplinaire de cet enfant. Réservé au secondaire — la
     * maternelle et le primaire ne prononcent pas de sanctions, une liste
     * vide suffit à le dire plutôt qu'une erreur.
     */
    public function sanctions(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if ($e->school?->type !== 'secondaire') {
            return ApiResponse::success(['total_sanctions' => 0, 'sanctions_en_cours' => 0, 'est_exclu' => false, 'motif_exclusion' => null, 'date_exclusion' => null, 'sanctions' => []]);
        }

        $sanctions = Sanction::where('eleve_id', $e->id)->with(['classe', 'enregistrePar'])->latest('date_sanction')->get();

        // Même règle que SanctionController::dossier() (vue personnel) : une
        // exclusion définitive confirmée, ou une exclusion temporaire
        // confirmée dont la période court encore.
        $exclusionActive = $sanctions->first(function (Sanction $s) {
            if ($s->statut !== 'confirmee') {
                return false;
            }

            return $s->type === 'exclusion_definitive'
                || ($s->type === 'exclusion_temporaire' && $s->date_fin && ! $s->date_fin->isPast());
        });

        return ApiResponse::success([
            'total_sanctions' => $sanctions->count(),
            'sanctions_en_cours' => $sanctions->where('statut', 'en_attente')->count(),
            'est_exclu' => $exclusionActive !== null,
            'motif_exclusion' => $exclusionActive?->motif,
            'date_exclusion' => $exclusionActive?->date_sanction?->format('Y-m-d'),
            'sanctions' => SanctionResource::collection($sanctions),
        ]);
    }

    /** Bulletin PDF du trimestre actif (ou demandé) — le même document que celui remis par l'école. */
    public function bulletin(Request $request, int $eleveId): Response
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if (! $e->classe) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $trimestre = $request->integer('trimestre_id')
            ? Trimestre::whereHas('anneeScolaire', fn($q) => $q->where('school_id', $e->school_id))->findOrFail($request->integer('trimestre_id'))
            : Trimestre::whereHas('anneeScolaire', fn($q) => $q->where('school_id', $e->school_id))->where('is_active', true)->firstOrFail();

        // Un parent ne voit un bulletin qu'une fois le conseil de classe tenu
        // et la classe publiée par le personnel (cf. BulletinController::publier) —
        // avant ça, la moyenne d'un élève peut encore bouger.
        $publie = BulletinPublication::where('classe_id', $e->classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->exists();

        if (! $publie) {
            return ApiResponse::error('Les bulletins de ce trimestre ne sont pas encore publiés.', 422);
        }

        // Le secondaire note par séquence (BulletinService) ; le primaire et
        // la maternelle par volets (BulletinPrimaireService) — même aiguillage
        // que côté enseignant/admin (cf. BulletinController / BulletinPrimaireController),
        // sans quoi un enfant hors secondaire fait planter la génération.
        if ($e->school?->type === 'secondaire') {
            $donnees = $this->bulletins->donneesClasse($e->classe, $trimestre, [$e->id]);
            $pdf = (new BulletinGenerator)->build($donnees);
        } else {
            $donnees = $this->bulletinsPrimaire->donneesClasse($e->classe, $trimestre, [$e->id]);
            $pdf = (new BulletinPrimaireGenerator)->build($donnees);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletin-' . Str::slug($e->nom_complet) . '.pdf"',
        ]);
    }

    /** Avancement du programme, matière par matière — celui de la classe : la progression n'est pas propre à un élève. */
    public function progression(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if (! $e->classe) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->progression->tauxClasse($e->classe));
    }

    /**
     * Programme d'une matière de l'enfant : titres des leçons et celle où
     * l'enseignant s'est arrêté. La matière doit appartenir à la classe de
     * l'enfant — sans ce contrôle, un identifiant deviné donnerait accès au
     * programme de n'importe quelle classe de l'établissement.
     */
    public function progressionMatiere(Request $request, int $eleveId, int $classeMatiereId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        abort_unless($e->classe, 404);

        $classeMatiere = ClasseMatiere::where('classe_id', $e->classe->id)
            ->with('matiere')
            ->findOrFail($classeMatiereId);

        return ApiResponse::success([
            'matiere' => $classeMatiere->matiere->nom,
            ...$this->progression->programmeParent($classeMatiere),
        ]);
    }

    /**
     * Leçons prévues de la classe pour la semaine précédente, en cours et
     * suivante, toutes matières confondues — ce que le parent a besoin de
     * suivre au jour le jour, plutôt que le programme complet de l'année.
     */
    public function leconsSemaine(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        if (! $e->classe) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->progression->leconsSemaine($e->classe));
    }

    /** Assiduité de l'enfant, journée par journée, sur l'année scolaire active — de quoi calculer un taux par jour, par mois ou par année côté écran. */
    public function assiduite(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);
        $annee = $this->anneeActive($e->school_id);

        if (! $annee) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->discipline->assiduiteEleve($e, $annee));
    }

    /** Absences relevées à l'appel, les plus récentes en tête. */
    public function absences(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $absences = Presence::where('eleve_id', $e->id)
            ->whereIn('statut', ['absent', 'retard'])
            ->with('seance:id,date_seance,heure_debut,classe_id')
            ->whereHas('seance', fn($q) => $q->orderByDesc('date_seance'))
            ->get()
            ->sortByDesc(fn($p) => $p->seance->date_seance)
            ->values()
            ->map(fn(Presence $p) => [
                'date' => $p->seance->date_seance?->format('Y-m-d'),
                'statut' => $p->statut,
                'motif' => $p->motif,
                'justifie' => (bool) $p->justifie,
                'remarque' => $p->remarque,
            ]);

        return ApiResponse::success($absences);
    }

    /** Justifications déposées pour cet enfant, en attente ou déjà appliquées à un pointage. */
    public function justifications(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        return ApiResponse::success($this->justifications->pourEnfant($e->id)->map(fn($j) => [
            'id' => $j->id,
            'date_debut' => $j->date_debut->format('Y-m-d'),
            'date_fin' => $j->date_fin->format('Y-m-d'),
            'motif' => $j->motif,
            'description' => $j->description,
            'statut' => $j->statut,
        ]));
    }

    public function soumettreJustification(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'motif' => ['required', 'in:maladie,scolarite,permission'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $tuteur = $this->tuteurDe($request);
            $justification = $this->justifications->soumettre($tuteur, $e, $data);
        } catch (RuntimeException $ex) {
            return ApiResponse::error($ex->getMessage(), 422);
        }

        return ApiResponse::created($justification, 'Justification transmise à l\'établissement.');
    }

    /** Fil d'observations partagé avec l'école — visible et alimentable des deux côtés. */
    public function observations(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $observations = Observation::where('eleve_id', $e->id)
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn(Observation $o) => [
                'id' => $o->id,
                'contenu' => $o->contenu,
                'auteur' => $o->user?->name,
                'origine' => $o->user?->hasRole('parent') ? 'parent' : 'ecole',
                'date' => $o->created_at->format('Y-m-d H:i'),
            ]);

        return ApiResponse::success($observations);
    }

    public function soumettreObservation(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $data = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);

        $observation = $this->observations->creer($e, $request->user(), $data['contenu']);

        return ApiResponse::created($observation, 'Observation transmise.');
    }

    /** Demande de révision d'identité/santé en attente pour cet enfant, s'il y en a une. */
    public function modificationEnAttente(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);
        $modification = $this->modifications->enAttentePour($e->id);

        return ApiResponse::success($modification ? $this->presenterModification($modification) : null);
    }

    /**
     * Historique complet des demandes de révision pour cet enfant, traitées ou
     * non — sans lui, une demande rejetée (ou déjà validée) disparaissait
     * purement et simplement de l'écran une fois traitée par l'établissement,
     * sans que le parent sache jamais ce qu'il en est advenu.
     */
    public function historiqueModifications(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        return ApiResponse::success(
            $this->modifications->historiquePour($e->id)->map($this->presenterModification(...))->values()
        );
    }

    private function presenterModification(ModificationEleve $modification): array
    {
        return [
            'id' => $modification->id,
            'donnees' => $modification->donnees,
            'statut' => $modification->statut,
            'motif_rejet' => $modification->motif_rejet,
            'created_at' => $modification->created_at->format('Y-m-d H:i'),
            'traite_le' => $modification->traite_le?->format('Y-m-d H:i'),
        ];
    }

    /** Le parent propose une révision d'identité/santé — appliquée seulement une fois validée par l'admin. */
    public function soumettreModification(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $data = $request->validate([
            'nom_complet' => ['sometimes', 'string', 'max:255'],
            'sexe' => ['sometimes', 'in:M,F'],
            'date_naissance' => ['sometimes', 'date'],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:255'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'numero_acte_naissance' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lieu_delivrance_acte' => ['sometimes', 'nullable', 'string', 'max:255'],
            'officier_etat_civil' => ['sometimes', 'nullable', 'string', 'max:255'],
            'groupe_sanguin' => ['sometimes', 'nullable', 'string', 'max:10'],
            'situation_sanitaire' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'aptitude' => ['sometimes', 'in:apte,inapte'],
            'allergies' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photo' => ['sometimes', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        // La photo n'est pas un champ texte du diff : elle est traitée et
        // posée « en attente » tout de suite (même recadrage que l'upload
        // direct), et seul son chemin de stockage voyage dans `donnees` —
        // appliqué à l'élève par ModificationEleveService::valider() une fois
        // la demande acceptée.
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->eleves->stockerPhotoPendante($e, $request->file('photo'));
        }
        unset($data['photo']);

        try {
            $tuteur = $this->tuteurDe($request);
            $modification = $this->modifications->soumettre($tuteur, $e, $data);
        } catch (RuntimeException $ex) {
            return ApiResponse::error($ex->getMessage(), 422);
        }

        return ApiResponse::created($modification, "Modification transmise, en attente de validation par l'établissement.");
    }

    private function anneeActive(int $schoolId): ?AnneeScolaire
    {
        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
    }

    private function tuteurDe(Request $request): Tuteur
    {
        $tuteur = Tuteur::where('user_id', $request->user()->id)->first();

        if (! $tuteur) {
            throw new RuntimeException('Aucune fiche tuteur associée à ce compte.');
        }

        return $tuteur;
    }
}
