<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BusAffectation;
use App\Models\Eleve;
use App\Services\BusService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BusAffectationController extends Controller
{
    public function __construct(private readonly BusService $service) {}

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
            // Scopé aux écoles accessibles (et non à l'école ambiante du
            // tenant) : en mode agrégé, l'élève ou le trajet visé peut
            // appartenir à n'importe laquelle d'entre elles, pas seulement à
            // celle retenue par défaut pour le compte.
            'eleve_id' => ['required_without:eleve_ids', 'integer', Rule::exists('eleves', 'id')->whereIn('school_id', Tenant::schoolIds())],
            'trajet_id' => ['required', 'integer', Rule::exists('bus_trajets', 'id')->whereIn('school_id', Tenant::schoolIds())],
            // Un arrêt n'appartenant pas au trajet choisi n'a pas de sens :
            // le champ « ramassera » un enfant sur un circuit qu'il ne suit pas.
            'arret_id' => ['nullable', 'integer', Rule::exists('bus_arrets', 'id')->where('trajet_id', $request->integer('trajet_id'))],
            'annee_scolaire_id' => ['nullable', 'integer', Rule::exists('annee_scolaires', 'id')->whereIn('school_id', Tenant::schoolIds())],
            'option_trajet' => ['required', Rule::in(BusAffectation::OPTIONS_TRAJET)],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(BusAffectation $affectation): array
    {
        $affectation->loadMissing(['eleve.classe', 'trajet.school', 'arret']);

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
            'school' => $affectation->trajet->school ? [
                'id' => $affectation->trajet->school->id,
                'name' => $affectation->trajet->school->name,
                'code' => $affectation->trajet->school->code,
                'type' => $affectation->trajet->school->type,
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
        ];
    }

    private function affectation(int $id): BusAffectation
    {
        return BusAffectation::whereHas('trajet', fn($q) => $q->forSchool(Tenant::schoolIds()))->findOrFail($id);
    }
}
