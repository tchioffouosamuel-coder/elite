<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\BusAffectation;
use App\Services\BusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BusAffectationController extends Controller
{
    use ScopedRules;

    public function __construct(private readonly BusService $service) {}

    public function index(Request $request): JsonResponse
    {
        $trajetId = $request->integer('trajet_id') ?: null;

        $affectations = $this->service->listerAffectations(app('tenant.school_id'), $trajetId);

        return ApiResponse::success($affectations->map(fn (BusAffectation $a) => $this->resumer($a))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');

        $donnees = $request->validate([
            'eleve_id' => ['required', 'integer', $this->scopedExists('eleves')],
            'trajet_id' => ['required', 'integer', $this->scopedExists('bus_trajets')],
            // Un arrêt n'appartenant pas au trajet choisi n'a pas de sens :
            // le champ « ramassera » un enfant sur un circuit qu'il ne suit pas.
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $request->integer('trajet_id'))],
            'annee_scolaire_id' => ['nullable', 'integer', $this->scopedExists('annee_scolaires')],
            'tarif_mensuel' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $affectation = $this->service->affecterEleve($schoolId, $donnees);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resumer($affectation), 'Élève affecté au trajet.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $affectation = $this->affectation($id);

        $donnees = $request->validate([
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $affectation->trajet_id)],
            'tarif_mensuel' => ['nullable', 'integer', 'min:0'],
            'statut' => ['nullable', 'in:actif,suspendu'],
        ]);

        $affectation = $this->service->modifierAffectation($affectation, $donnees);

        return ApiResponse::success($this->resumer($affectation), 'Affectation mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->retirerAffectation($this->affectation($id));

        return ApiResponse::success(null, 'Affectation retirée.');
    }

    /** @return array<string, mixed> */
    private function resumer(BusAffectation $affectation): array
    {
        $affectation->loadMissing(['eleve.classe', 'trajet', 'arret']);

        return [
            'id' => $affectation->id,
            'statut' => $affectation->statut,
            'tarif_mensuel' => $affectation->tarif_mensuel,
            'eleve' => [
                'id' => $affectation->eleve->id,
                'nom_complet' => $affectation->eleve->nom_complet,
                'matricule' => $affectation->eleve->matricule,
                'classe' => $affectation->eleve->classe?->nom,
            ],
            'trajet' => ['id' => $affectation->trajet->id, 'nom' => $affectation->trajet->nom],
            'arret' => $affectation->arret ? ['id' => $affectation->arret->id, 'nom' => $affectation->arret->nom] : null,
        ];
    }

    private function affectation(int $id): BusAffectation
    {
        return BusAffectation::whereHas('trajet', fn ($q) => $q->forSchool(app('tenant.school_id')))->findOrFail($id);
    }
}
