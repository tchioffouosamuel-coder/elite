<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\User;
use App\Services\EmploiDuTempsService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeanceController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    public function index(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $seances = Seance::where('classe_id', $classe->id)
            ->when($request->date('date_debut'), fn ($q, $d) => $q->whereDate('date_seance', '>=', $d))
            ->when($request->date('date_fin'), fn ($q, $d) => $q->whereDate('date_seance', '<=', $d))
            ->when($request->integer('trimestre_id'), fn ($q, $id) => $q->where('trimestre_id', $id))
            ->with(['classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->withCount(['presences as absents_count' => fn ($q) => $q->whereIn('statut', ['absent', 'renvoye'])])
            ->orderByDesc('date_seance')->orderBy('heure_debut')
            ->get();

        return ApiResponse::success($seances->map(fn (Seance $s) => $this->presenter($s, $request->user())));
    }

    public function store(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $data = $request->validate([
            'classe_matiere_id' => ['required', 'integer'],
            'trimestre_id' => ['nullable', 'integer'],
            'date_seance' => ['required', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:50'],
            'contenu' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(
            ClasseMatiere::where('classe_id', $classe->id)->whereKey($data['classe_matiere_id'])->exists(),
            422,
            "Cette matière n'est pas affectée à la classe."
        );

        $seance = Seance::create([...$data, 'classe_id' => $classe->id, 'school_id' => $classe->school_id]);

        return ApiResponse::created(
            $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant'), $request->user()),
            'Séance créée.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $seance = $this->seance($id);

        $data = $request->validate([
            'contenu' => ['nullable', 'string', 'max:2000'],
            'salle' => ['nullable', 'string', 'max:50'],
            'statut' => ['nullable', 'in:prevue,effectuee,annulee'],
        ]);

        $seance->update($data);

        return ApiResponse::success(
            $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant'), $request->user()),
            'Séance mise à jour.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->seance($id)->delete();

        return ApiResponse::success(message: 'Séance supprimée.');
    }

    /** Feuille d'appel de la séance. */
    public function appel(Request $request, int $id): JsonResponse
    {
        $seance = $this->seance($id);

        $classes = $seance->classesConcernees();

        return ApiResponse::success([
            'seance' => $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant'), $request->user()),
            /*
             * Un cours en tronc commun réunit plusieurs classes : l'écran doit
             * l'annoncer, sinon un enseignant qui voit trois fois plus d'élèves
             * que prévu croit à une erreur.
             */
            'tronc_commun' => $classes->count() > 1,
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom])->values(),
            'verrouille' => $seance->appelVerrouillePour($request->user()),
            'modifiable_jusqua' => $seance->appel_verrouille_le
                ?->addMinutes(Seance::MINUTES_VERROUILLAGE_APPEL)
                ?->toIso8601String(),
            'lignes' => $this->service->feuilleAppel($seance)->map(fn ($ligne) => [
                'eleve_id' => $ligne['eleve']->id,
                'nom_complet' => $ligne['eleve']->nom_complet,
                'matricule' => $ligne['eleve']->matricule,
                'classe' => $ligne['classe']['nom'] ?? null,
                'statut' => $ligne['statut'],
                'motif' => $ligne['motif'],
                'justifie' => $ligne['justifie'],
                'remarque' => $ligne['remarque'],
                'pointe' => $ligne['pointe'],
            ]),
        ]);
    }

    public function enregistrerAppel(Request $request, int $id): JsonResponse
    {
        $seance = $this->seance($id);

        abort_unless(
            $seance->aCommence(),
            403,
            "Cette séance n'a pas encore commencé — l'appel sera possible à partir de {$seance->heure_debut}."
        );

        abort_if(
            $seance->appelVerrouillePour($request->user()),
            403,
            "L'appel de cette séance est verrouillé depuis plus de ".Seance::MINUTES_VERROUILLAGE_APPEL." minutes. Contactez le Surveillant Général pour une correction."
        );

        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.eleve_id' => ['required', 'integer'],
            'lignes.*.statut' => ['required', 'in:present,absent'],
            // Une absence sans motif ne se traite pas : le surveillant général
            // ne saurait pas s'il faut relancer la famille ou classer l'affaire.
            'lignes.*.motif' => ['nullable', 'required_if:lignes.*.statut,absent', Rule::in(Presence::MOTIFS)],
            'lignes.*.remarque' => ['nullable', 'string', 'max:255'],
            // Requis pour un enseignant (cf. User::doitScannerQrPourValiderAppel()),
            // même règle que MaJourneeController::enregistrer() : le token affiché
            // dans la salle, comparé tel quel à Classe::qr_token — pas de passage
            // par resoudreQr() pour rester rejouable hors ligne (l'appel de cet
            // écran passe par l'outbox de synchronisation).
            'qr_token' => ['nullable', 'string'],
        ]);

        $qrValide = $data['qr_token'] ?? null;
        $qrValide = $qrValide !== null
            && $seance->classe->qr_token !== null
            && hash_equals($seance->classe->qr_token, $qrValide);

        abort_if(
            $request->user()->doitScannerQrPourValiderAppel() && ! $qrValide,
            403,
            "Scannez le QR code de la salle avant de valider — c'est ce qui prouve que vous y étiez."
        );

        $total = $this->service->enregistrerAppel($seance, $data['lignes']);

        return ApiResponse::success(['enregistres' => $total], "Appel enregistré ({$total} élève(s)).");
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(Tenant::schoolIds())->findOrFail($id);
    }

    private function seance(int $id): Seance
    {
        return Seance::forSchool(Tenant::schoolIds())->findOrFail($id);
    }

    private function presenter(Seance $seance, User $user): array
    {
        return [
            'id' => $seance->id,
            'classe_id' => $seance->classe_id,
            'classe_matiere_id' => $seance->classe_matiere_id,
            'matiere' => $seance->classeMatiere?->matiere?->nom,
            'enseignant' => $seance->classeMatiere?->enseignant?->nom_complet,
            'date_seance' => $seance->date_seance?->toDateString(),
            'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
            'heure_fin' => substr((string) $seance->heure_fin, 0, 5),
            'salle' => $seance->salle,
            'contenu' => $seance->contenu,
            'statut' => $seance->statut,
            'absents' => $seance->absents_count,
            'verrouille' => $seance->appelVerrouillePour($user),
            'demarree' => $seance->aCommence(),
        ];
    }
}
