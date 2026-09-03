<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\BulletinPublication;
use App\Models\Presence;
use App\Models\Sanction;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\VisiteInfirmerie;
use App\Http\Resources\Api\V1\SanctionResource;
use App\Http\Resources\Api\V1\VisiteInfirmerieResource;
use App\Services\BulletinPrimaireService;
use App\Services\BulletinService;
use App\Services\DisciplineService;
use App\Services\EmploiDuTempsService;
use App\Support\EleveAccess;
use App\Support\Pdf\BulletinGenerator;
use App\Support\Pdf\BulletinPrimaireGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portail élève : chacune de ces actions ne porte que sur la fiche du
 * compte connecté (cf. {@see EleveAccess}) — pendant de
 * {@see ParentEspaceController}, en lecture seule et sans le volet finance,
 * réservé au tuteur. `notes()` délègue à {@see NoteEleveController} plutôt
 * que de dupliquer le calcul des moyennes/classements.
 */
class EleveEspaceController extends Controller
{
    public function __construct(
        private readonly NoteEleveController $notesController,
        private readonly BulletinService $bulletins,
        private readonly BulletinPrimaireService $bulletinsPrimaire,
        private readonly DisciplineService $discipline,
        private readonly EmploiDuTempsService $emploiDuTemps,
    ) {}

    /** Dossier de l'élève connecté : identité, santé, contacts des tuteurs — tout sur un seul écran. */
    public function moi(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

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
            'redoublant' => (bool) $e->redoublant,
            'statut' => $e->statut,
            'classe' => $e->classe ? ['id' => $e->classe->id, 'nom' => $e->classe->nom, 'sous_systeme' => $e->classe->sousSysteme?->nom] : null,
            'school' => $e->school ? ['id' => $e->school->id, 'name' => $e->school->name, 'type' => $e->school->type] : null,
            'sante' => [
                'groupe_sanguin' => $e->groupe_sanguin,
                'situation_sanitaire' => $e->situation_sanitaire,
                'aptitude' => $e->aptitude,
                'allergies' => $e->allergies,
            ],
            'tuteurs' => $e->tuteurs->map(fn (Tuteur $t) => [
                'nom_complet' => $t->nom_complet,
                'telephone' => $t->telephone,
                'lien_parente' => $t->pivot->lien_parente,
            ]),
        ]);
    }

    /** Notes de l'élève connecté, mêmes moyennes et classements que le personnel — cf. NoteEleveController::index(). */
    public function notes(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

        return $this->notesController->index($request, $e->id);
    }

    /** Emploi du temps de la classe de l'élève connecté. */
    public function emploiDuTemps(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

        if (! $e->classe) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->emploiDuTemps->grille($e->classe)->map(EmploiDuTempsService::presenter(...)));
    }

    /** Visites à l'infirmerie de l'élève connecté, les plus récentes en tête. */
    public function visitesInfirmerie(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

        $visites = VisiteInfirmerie::where('eleve_id', $e->id)
            ->with(['eleve.school', 'classe', 'enregistrePar', 'malaises', 'materiels.article'])
            ->latest('date_visite')
            ->get();

        return ApiResponse::success(VisiteInfirmerieResource::collection($visites));
    }

    /** Dossier disciplinaire de l'élève connecté — réservé au secondaire, comme côté parent. */
    public function sanctions(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

        if ($e->school?->type !== 'secondaire') {
            return ApiResponse::success(['total_sanctions' => 0, 'sanctions_en_cours' => 0, 'est_exclu' => false, 'motif_exclusion' => null, 'date_exclusion' => null, 'sanctions' => []]);
        }

        $sanctions = Sanction::where('eleve_id', $e->id)->with(['classe', 'enregistrePar'])->latest('date_sanction')->get();

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

    /** Absences relevées à l'appel, les plus récentes en tête. */
    public function absences(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());

        $absences = Presence::where('eleve_id', $e->id)
            ->whereIn('statut', ['absent', 'retard'])
            ->with('seance:id,date_seance,heure_debut,classe_id')
            ->whereHas('seance', fn ($q) => $q->orderByDesc('date_seance'))
            ->get()
            ->sortByDesc(fn (Presence $p) => $p->seance->date_seance)
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

    /** Assiduité de l'élève connecté, journée par journée, sur l'année scolaire active. */
    public function assiduite(Request $request): JsonResponse
    {
        $e = EleveAccess::assertMoi($request->user());
        $annee = AnneeScolaire::where('school_id', $e->school_id)->where('is_active', true)->first();

        if (! $annee) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->discipline->assiduiteEleve($e, $annee));
    }

    /** Bulletin PDF du trimestre actif (ou demandé) — le même document que celui remis par l'école. */
    public function bulletin(Request $request): Response
    {
        $e = EleveAccess::assertMoi($request->user());

        if (! $e->classe) {
            return ApiResponse::error("Vous n'êtes affecté à aucune classe.", 422);
        }

        $trimestre = $request->integer('trimestre_id')
            ? Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $e->school_id))->findOrFail($request->integer('trimestre_id'))
            : Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $e->school_id))->where('is_active', true)->firstOrFail();

        $publie = BulletinPublication::where('classe_id', $e->classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->exists();

        if (! $publie) {
            return ApiResponse::error('Les bulletins de ce trimestre ne sont pas encore publiés.', 422);
        }

        if ($e->school?->type === 'secondaire') {
            $donnees = $this->bulletins->donneesClasse($e->classe, $trimestre, [$e->id]);
            $pdf = (new BulletinGenerator)->build($donnees);
        } else {
            $donnees = $this->bulletinsPrimaire->donneesClasse($e->classe, $trimestre, [$e->id]);
            $pdf = (new BulletinPrimaireGenerator)->build($donnees);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletin-'.Str::slug($e->nom_complet).'.pdf"',
        ]);
    }
}
