<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use Carbon\CarbonImmutable;
use App\Models\ClasseMatiere;
use App\Models\Presence;
use App\Models\ProgressionItem;
use App\Services\MaJourneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * « Ma journée » : l'enseignant déclare les leçons qu'il vient de traiter et
 * fait l'appel, en une seule page.
 */
class MaJourneeController extends Controller
{
    public function __construct(private readonly MaJourneeService $service) {}

    /** Classes et matières sur lesquelles l'enseignant connecté intervient. */
    public function affectations(Request $request): JsonResponse
    {
        // `date()` plante sur une valeur non scalaire (ex. `date[]=...`) au lieu
        // de la rejeter proprement : on valide nous-mêmes, comme `enregistrer()`
        // le fait déjà plus bas, pour ne renvoyer qu'un 422 dans ce cas.
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? null;

        if ($date !== null && ! CarbonImmutable::parse($date)->isToday() && ! $request->user()->estPersonnelDirection()) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success(
            $this->service->mesAffectations($request->user(), app('tenant.school_id'), $date)
        );
    }

    public function feuille(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($request, $classeMatiereId);

        if ($classeMatiere instanceof JsonResponse) {
            return $classeMatiere;
        }

        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? now()->format('Y-m-d');

        try {
            $seance = $this->service->seanceDuJour($classeMatiere, $date);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->service->feuilleDuJour($classeMatiere, $seance, $request->user()));
    }

    public function enregistrer(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($request, $classeMatiereId);

        if ($classeMatiere instanceof JsonResponse) {
            return $classeMatiere;
        }

        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'lecons' => ['present', 'array'],
            'lecons.*' => ['integer'],
            'appel' => ['present', 'array'],
            'appel.*.eleve_id' => ['required', 'integer'],
            'appel.*.statut' => ['required', 'in:present,absent'],
            // Une absence sans motif ne se traite pas en aval : le surveillant
            // général ne saurait pas s'il faut relancer la famille.
            'appel.*.motif' => ['nullable', 'required_if:appel.*.statut,absent', Rule::in(Presence::MOTIFS)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'donnees_personnalisees' => ['nullable', 'array'],
            // Requis pour un enseignant (cf. User::doitScannerQrPourValiderAppel()) :
            // c'est le token affiché dans la salle, qui prouve qu'il y était au
            // moment de la validation — comparé tel quel à `Classe::qr_token`,
            // sans passer par `resoudreQr()` pour rester rejouable hors ligne.
            'qr_token' => ['nullable', 'string'],
        ]);

        $qrValide = $data['qr_token'] ?? null;
        $qrValide = $qrValide !== null
            && $classeMatiere->classe->qr_token !== null
            && hash_equals($classeMatiere->classe->qr_token, $qrValide);

        if ($request->user()->doitScannerQrPourValiderAppel() && ! $qrValide) {
            return ApiResponse::forbidden(
                "Scannez le QR code de la salle avant de valider — c'est ce qui prouve que vous y étiez."
            );
        }

        $date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : now()->format('Y-m-d');

        if ($request->user()->estEnseignant() && $date !== now()->format('Y-m-d')) {
            return ApiResponse::error(
                "Vous ne pouvez déclarer que la journée en cours.",
                403,
                ['code' => 'seance_hors_jour']
            );
        }

        try {
            $seance = $this->service->seanceDuJour($classeMatiere, $date);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        // Un enseignant ne déclare que la journée en cours ; la direction, déjà
        // dispensée du QR (cf. doitScannerQrPourValiderAppel()), l'est aussi de
        // cette contrainte pour pouvoir corriger une date antérieure.
        if (! $seance->estAujourdhui() && ! $request->user()->estPersonnelDirection()) {
            return ApiResponse::error(
                "Vous ne pouvez déclarer que la journée en cours. Contactez la direction pour une correction sur une autre date.",
                403,
                ['code' => 'seance_hors_jour']
            );
        }

        $resultat = $this->service->enregistrer(
            $classeMatiere,
            $seance,
            $data['lecons'],
            $data['appel'],
            $request->user(),
            $data['observations'] ?? null,
            $data['donnees_personnalisees'] ?? [],
            $qrValide,
        );

        return ApiResponse::success(
            [...$resultat, ...$this->service->feuilleDuJour($classeMatiere, $seance->refresh(), $request->user())],
            "Journée enregistrée : {$resultat['lecons']} leçon(s), {$resultat['eleves']} élève(s) pointé(s)."
        );
    }

    /**
     * Détails prévus d'une leçon (fiche de préparation), pour l'afficher avant
     * de la marquer comme faite — mêmes champs que l'écran de progression
     * pédagogique, en lecture seule ici.
     */
    public function lecon(Request $request, int $classeMatiereId, int $leconId): JsonResponse
    {
        $classeMatiere = $this->affectation($request, $classeMatiereId);

        if ($classeMatiere instanceof JsonResponse) {
            return $classeMatiere;
        }

        $lecon = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->lecons()
            ->with('parent.parent', 'sequence')
            ->find($leconId);

        if (! $lecon) {
            return ApiResponse::notFound("Cette leçon n'existe pas dans cette affectation.");
        }

        return ApiResponse::success([
            'id' => $lecon->id,
            'titre' => $lecon->titre,
            'chemin' => collect([$lecon->parent?->parent?->titre, $lecon->parent?->titre])
                ->filter()->implode(' › '),
            'sequence' => $lecon->sequence?->libelle,
            'description' => $lecon->description,
            ...collect(ProgressionItem::CHAMPS_FICHE)->mapWithKeys(fn($champ) => [$champ => $lecon->$champ])->all(),
            'duree_prevue' => $lecon->duree_prevue,
            'colonnes_libres' => $lecon->colonnes_libres ?? [],
        ]);
    }

    /** Heures de cours prévues vs réalisées de l'enseignant connecté, depuis le début de l'année. */
    public function couverture(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->heuresCouverture($request->user(), app('tenant.school_id')));
    }

    /**
     * Résout le cours en train de se tenir dans une salle, à partir du QR
     * code affiché au mur — c'est l'équivalent numérique du scan.
     */
    public function resoudreQr(Request $request, string $token): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->where('qr_token', $token)->first();

        if (! $classe) {
            return ApiResponse::notFound('Ce QR code ne correspond à aucune salle connue.');
        }

        $classeMatiere = $this->service->creneauActuel($classe);

        if (! $classeMatiere) {
            return ApiResponse::error("Aucun cours n'est prévu à cette heure dans {$classe->nom}.", 422);
        }

        if (! $this->service->peutIntervenir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Ce cours n'est pas parmi vos affectations.");
        }

        return ApiResponse::success([
            'classe_matiere_id' => $classeMatiere->id,
            'classe_id' => $classe->id,
            'classe' => $classe->nom,
            'matiere' => $classeMatiere->matiere?->nom,
            'qr_token' => $token,
        ]);
    }

    private function affectation(Request $request, int $id): ClasseMatiere|JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(app('tenant.school_id'))
            ->with(['classe', 'matiere'])
            ->findOrFail($id);

        if (! $this->service->peutIntervenir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'intervenez pas sur cette classe.");
        }

        return $classeMatiere;
    }
}
