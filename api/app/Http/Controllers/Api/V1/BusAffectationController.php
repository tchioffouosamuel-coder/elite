<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\BusAffectation;
use App\Models\Eleve;
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

    /** Tous les élèves de l'école, souscription bus incluse si elle existe — filtrable par classe. */
    public function eleves(Request $request): JsonResponse
    {
        $eleves = $this->service->listerElevesTransport(
            app('tenant.school_id'),
            $request->integer('classe_id') ?: null,
            $request->integer('annee_scolaire_id') ?: null,
        );

        return ApiResponse::success($eleves->map(fn (Eleve $e) => $this->resumerEleve($e))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $donnees = $this->validerSouscription($request);

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
        $schoolId = app('tenant.school_id');

        $data = $request->validate([
            'eleve_ids' => ['required', 'array', 'min:1'],
            'eleve_ids.*' => ['integer', $this->scopedExists('eleves')],
        ]);

        $donnees = $this->validerSouscription($request);
        unset($donnees['eleve_id']);

        $resultat = $this->service->souscrireEnLot($schoolId, $data['eleve_ids'], $donnees);

        $message = "{$resultat['souscrits']} élève(s) souscrit(s) au bus.";
        if ($resultat['ignores'] !== []) {
            $message .= ' Déjà affecté(s) : '.implode(', ', $resultat['ignores']).'.';
        }

        return ApiResponse::success($resultat, $message);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $affectation = $this->affectation($id);

        $donnees = $request->validate([
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $affectation->trajet_id)],
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

    /**
     * Règles communes à la souscription individuelle et en lot — le tarif ne
     * s'y trouve jamais : il vient du trajet, jamais d'une saisie.
     */
    private function validerSouscription(Request $request): array
    {
        return $request->validate([
            'eleve_id' => ['required_without:eleve_ids', 'integer', $this->scopedExists('eleves')],
            'trajet_id' => ['required', 'integer', $this->scopedExists('bus_trajets')],
            // Un arrêt n'appartenant pas au trajet choisi n'a pas de sens :
            // le champ « ramassera » un enfant sur un circuit qu'il ne suit pas.
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $request->integer('trajet_id'))],
            'annee_scolaire_id' => ['nullable', 'integer', $this->scopedExists('annee_scolaires')],
            'option_trajet' => ['required', Rule::in(BusAffectation::OPTIONS_TRAJET)],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(BusAffectation $affectation): array
    {
        $affectation->loadMissing(['eleve.classe', 'trajet', 'arret']);

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
            'arret' => $affectation->arret ? ['id' => $affectation->arret->id, 'nom' => $affectation->arret->nom] : null,
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
            'bus' => $affectation ? [
                'affectation_id' => $affectation->id,
                'trajet' => ['id' => $affectation->trajet->id, 'nom' => $affectation->trajet->nom],
                'arret' => $affectation->arret ? ['id' => $affectation->arret->id, 'nom' => $affectation->arret->nom] : null,
                'option_trajet' => $affectation->option_trajet,
                'tarif_mensuel' => $affectation->tarif_mensuel,
                'statut_paiement' => $affectation->statut_paiement,
            ] : null,
        ];
    }

    private function affectation(int $id): BusAffectation
    {
        return BusAffectation::whereHas('trajet', fn ($q) => $q->forSchool(app('tenant.school_id')))->findOrFail($id);
    }
}
