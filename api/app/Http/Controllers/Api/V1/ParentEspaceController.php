<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Moratoire;
use App\Models\Observation;
use App\Models\Presence;
use App\Models\Sanction;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\VisiteInfirmerie;
use App\Http\Resources\Api\V1\SanctionResource;
use App\Http\Resources\Api\V1\VisiteInfirmerieResource;
use App\Services\BulletinService;
use App\Services\EmploiDuTempsService;
use App\Services\JustificationAbsenceService;
use App\Services\ModificationEleveService;
use App\Services\ProgressionService;
use App\Services\ScolariteService;
use App\Support\ParentAccess;
use App\Support\Pdf\BulletinGenerator;
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
        private readonly ProgressionService $progression,
        private readonly JustificationAbsenceService $justifications,
        private readonly ModificationEleveService $modifications,
        private readonly EmploiDuTempsService $emploiDuTemps,
    ) {}

    /** Enfants du compte connecté — la liste qui ouvre le portail. */
    public function mesEnfants(Request $request): JsonResponse
    {
        $enfants = ParentAccess::enfants($request->user());

        return ApiResponse::success($enfants->map(fn (Eleve $e) => [
            'id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nom_complet,
            'sexe' => $e->sexe,
            'photo_url' => $e->photo_path ? asset('storage/'.$e->photo_path) : null,
            'classe' => $e->classe ? ['id' => $e->classe->id, 'nom' => $e->classe->nom] : null,
            'school' => $e->school ? ['id' => $e->school->id, 'name' => $e->school->name] : null,
        ]));
    }

    /** Dossier consolidé d'un enfant : identité, acte de naissance, santé, tuteurs — tout sur un seul écran. */
    public function enfant(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        return ApiResponse::success([
            'id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nom_complet,
            'sexe' => $e->sexe,
            'date_naissance' => $e->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $e->lieu_naissance,
            'nationalite' => $e->nationalite,
            'adresse' => $e->adresse,
            'photo_url' => $e->photo_path ? asset('storage/'.$e->photo_path) : null,
            'photo_tenue_url' => $e->photo_tenue_path ? asset('storage/'.$e->photo_tenue_path) : null,
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
            'tuteurs' => $e->tuteurs->map(fn (Tuteur $t) => [
                'id' => $t->id,
                'nom_complet' => $t->nom_complet,
                'telephone' => $t->telephone,
                'email' => $t->email,
                'profession' => $t->profession,
                'lieu_service' => $t->lieu_service,
                'adresse' => $t->adresse,
                'lien_parente' => $t->pivot->lien_parente,
                'is_principal' => (bool) $t->pivot->is_principal,
            ]),
        ]);
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
        $dossier->loadMissing(['fraisAnnexes', 'versements' => fn ($q) => $q->valides()->with('lignes'), 'busAffectations.trajet']);

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
            'versements' => $dossier->versements->whereNull('annule_le')->map(fn ($v) => [
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
            ? Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $e->school_id))->findOrFail($request->integer('trimestre_id'))
            : Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $e->school_id))->where('is_active', true)->firstOrFail();

        $donnees = $this->bulletins->donneesClasse($e->classe, $trimestre, [$e->id]);

        return response((new BulletinGenerator)->build($donnees), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletin-'.Str::slug($e->nom_complet).'.pdf"',
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

    /** Absences relevées à l'appel, les plus récentes en tête. */
    public function absences(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);

        $absences = Presence::where('eleve_id', $e->id)
            ->whereIn('statut', ['absent', 'retard'])
            ->with('seance:id,date_seance,heure_debut,classe_id')
            ->whereHas('seance', fn ($q) => $q->orderByDesc('date_seance'))
            ->get()
            ->sortByDesc(fn ($p) => $p->seance->date_seance)
            ->values()
            ->map(fn (Presence $p) => [
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

        return ApiResponse::success($this->justifications->pourEnfant($e->id)->map(fn ($j) => [
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
            ->map(fn (Observation $o) => [
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

        $observation = Observation::create([
            'school_id' => $e->school_id,
            'eleve_id' => $e->id,
            'user_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        return ApiResponse::created($observation, 'Observation transmise.');
    }

    /** Demande de révision d'identité/santé en attente pour cet enfant, s'il y en a une. */
    public function modificationEnAttente(Request $request, int $eleveId): JsonResponse
    {
        $e = ParentAccess::assertEnfant($request->user(), $eleveId);
        $modification = $this->modifications->enAttentePour($e->id);

        return ApiResponse::success($modification ? [
            'id' => $modification->id,
            'donnees' => $modification->donnees,
            'statut' => $modification->statut,
            'created_at' => $modification->created_at->format('Y-m-d H:i'),
        ] : null);
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
        ]);

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
