<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\ConseilClasseDecision;
use App\Services\ConseilClasseService;
use App\Support\Pdf\ProcesVerbalConseilGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conseil de classe de fin d'année — un par (classe, année scolaire). Voir
 * {@see ConseilClasseService} pour la logique (seuil, ajustements, validation).
 */
class ConseilClasseController extends Controller
{
    public function __construct(private readonly ConseilClasseService $service) {}

    /** Prépare (ou renvoie) le conseil de la classe pour l'année demandée — l'année active par défaut. */
    public function show(int $classeId, Request $request): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $annee = $this->resoudreAnnee($classe, $request);

        $conseil = $this->service->preparer($classe, $annee);

        return ApiResponse::success($this->presenter($conseil));
    }

    public function definirSeuil(int $id, Request $request): JsonResponse
    {
        $conseil = ConseilClasse::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate([
            'seuil_moyenne' => ['required', 'numeric', 'min:0', 'max:20'],
            'motif' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $conseil = $this->service->definirSeuil($conseil, (float) $data['seuil_moyenne'], $data['motif'] ?? null);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($conseil), 'Seuil mis à jour.');
    }

    public function definirDestination(int $id, Request $request): JsonResponse
    {
        $conseil = ConseilClasse::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['classe_destination_id' => ['nullable', 'integer', 'exists:classes,id']]);

        try {
            $conseil = $this->service->definirClasseDestination($conseil, $data['classe_destination_id'] ?? null);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($conseil), 'Classe de destination mise à jour.');
    }

    public function exclure(int $decisionId, Request $request): JsonResponse
    {
        $decision = ConseilClasseDecision::whereHas('conseilClasse', fn ($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($decisionId);
        $data = $request->validate(['motif' => ['required', 'string', 'max:2000']]);

        try {
            $this->service->exclure($decision, $data['motif']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($decision->conseilClasse->fresh()), 'Élève exclu.');
    }

    public function gracier(int $decisionId, Request $request): JsonResponse
    {
        $decision = ConseilClasseDecision::whereHas('conseilClasse', fn ($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($decisionId);
        $data = $request->validate(['motif' => ['required', 'string', 'max:2000']]);

        try {
            $this->service->gracier($decision, $data['motif']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($decision->conseilClasse->fresh()), 'Élève gracié.');
    }

    public function annulerAjustement(int $decisionId): JsonResponse
    {
        $decision = ConseilClasseDecision::whereHas('conseilClasse', fn ($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($decisionId);

        try {
            $this->service->annulerAjustement($decision);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($decision->conseilClasse->fresh()), 'Ajustement annulé.');
    }

    public function valider(int $id, Request $request): JsonResponse
    {
        $conseil = ConseilClasse::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $conseil = $this->service->valider($conseil, $request->user());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->presenter($conseil), 'Conseil de classe validé.');
    }

    public function pv(int $id): Response
    {
        $conseil = ConseilClasse::forSchool(Tenant::schoolIds())
            ->with(['school', 'classe', 'classeDestination', 'anneeScolaire', 'decisions.eleve'])
            ->findOrFail($id);

        $decisions = $conseil->decisions;

        $ligne = fn ($d) => [
            'nom_complet' => $d->eleve->nom_complet,
            'matricule' => $d->eleve->matricule,
            'moyenne_annuelle' => $d->moyenne_annuelle,
            'gracie' => $d->gracie,
            'motif' => $d->motif,
        ];

        $pdf = (new ProcesVerbalConseilGenerator)->build([
            'school' => $conseil->school,
            'classe' => $conseil->classe,
            'annee' => $conseil->anneeScolaire,
            'seuil_moyenne' => (float) $conseil->seuil_moyenne,
            'motif_seuil' => $conseil->motif_seuil,
            'classe_destination' => $conseil->classeDestination?->nom,
            'valide_le' => $conseil->valide_le?->format('d/m/Y'),
            'admis' => $decisions->where('decision_finale', 'admis')->map($ligne)->values()->all(),
            'redoublants' => $decisions->where('decision_finale', 'redouble')->map($ligne)->values()->all(),
            'exclus' => $decisions->where('decision_finale', 'exclu')->map($ligne)->values()->all(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pv-conseil-'.$conseil->classe->nom.'.pdf"',
        ]);
    }

    private function resoudreAnnee(Classe $classe, Request $request): AnneeScolaire
    {
        if ($anneeId = $request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $classe->school_id)->findOrFail($anneeId);
        }

        return AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->firstOrFail();
    }

    private function presenter(ConseilClasse $conseil): array
    {
        $conseil->loadMissing(['classe', 'classeDestination', 'anneeScolaire', 'decisions.eleve']);

        return [
            'id' => $conseil->id,
            'classe' => ['id' => $conseil->classe->id, 'nom' => $conseil->classe->nom],
            'annee_scolaire' => ['id' => $conseil->anneeScolaire->id, 'libelle' => $conseil->anneeScolaire->libelle],
            'seuil_moyenne' => (float) $conseil->seuil_moyenne,
            'motif_seuil' => $conseil->motif_seuil,
            'classe_destination' => $conseil->classeDestination ? ['id' => $conseil->classeDestination->id, 'nom' => $conseil->classeDestination->nom] : null,
            'statut' => $conseil->statut,
            'valide_le' => $conseil->valide_le?->toIso8601String(),
            'decisions' => $conseil->decisions->map(fn (ConseilClasseDecision $d) => [
                'id' => $d->id,
                'eleve' => ['id' => $d->eleve->id, 'matricule' => $d->eleve->matricule, 'nom_complet' => $d->eleve->nom_complet],
                'moyenne_annuelle' => $d->moyenne_annuelle,
                'decision_defaut' => $d->decision_defaut,
                'decision_finale' => $d->decision_finale,
                'gracie' => $d->gracie,
                'motif' => $d->motif,
            ])->values(),
        ];
    }
}
