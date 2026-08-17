<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Presence;
use App\Services\MaJourneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        return ApiResponse::success(
            $this->service->mesAffectations($request->user(), app('tenant.school_id'))
        );
    }

    public function feuille(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($request, $classeMatiereId);

        if ($classeMatiere instanceof JsonResponse) {
            return $classeMatiere;
        }

        $date = $request->date('date')?->format('Y-m-d') ?? now()->format('Y-m-d');
        $seance = $this->service->seanceDuJour($classeMatiere, $date);

        return ApiResponse::success($this->service->feuilleDuJour($classeMatiere, $seance));
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
            'appel.*.statut' => ['required', 'in:present,absent,retard,renvoye'],
            // Une absence sans motif ne se traite pas en aval : le surveillant
            // général ne saurait pas s'il faut relancer la famille.
            'appel.*.motif' => ['nullable', 'required_if:appel.*.statut,absent', Rule::in(Presence::MOTIFS)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'donnees_personnalisees' => ['nullable', 'array'],
        ]);

        $date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : now()->format('Y-m-d');
        $seance = $this->service->seanceDuJour($classeMatiere, $date);

        $resultat = $this->service->enregistrer(
            $classeMatiere,
            $seance,
            $data['lecons'],
            $data['appel'],
            $data['observations'] ?? null,
            $data['donnees_personnalisees'] ?? [],
        );

        return ApiResponse::success(
            [...$resultat, ...$this->service->feuilleDuJour($classeMatiere, $seance->refresh())],
            "Journée enregistrée : {$resultat['lecons']} leçon(s), {$resultat['eleves']} élève(s) pointé(s)."
        );
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
            'classe' => $classe->nom,
            'matiere' => $classeMatiere->matiere?->nom,
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
