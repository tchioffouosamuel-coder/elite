<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Services\BusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusTrajetController extends Controller
{
    public function __construct(private readonly BusService $service) {}

    public function index(): JsonResponse
    {
        $trajets = $this->service->listerTrajets(app('tenant.school_id'));

        return ApiResponse::success($trajets->map(fn (BusTrajet $t) => $this->resumer($t))->values());
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->resumerDetail($this->trajet($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'vehicule_id' => ['nullable', 'integer', Rule::exists('bus_vehicules', 'id')->where('school_id', app('tenant.school_id'))],
            'tarif_aller_simple' => ['nullable', 'integer', 'min:0'],
            'tarif_retour_simple' => ['nullable', 'integer', 'min:0'],
            'tarif_aller_retour' => ['nullable', 'integer', 'min:0'],
        ]);

        $trajet = $this->service->creerTrajet(app('tenant.school_id'), $donnees);

        return ApiResponse::created($this->resumer($trajet), 'Trajet créé.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $trajet = $this->trajet($id);

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'vehicule_id' => ['nullable', 'integer', Rule::exists('bus_vehicules', 'id')->where('school_id', app('tenant.school_id'))],
            'tarif_aller_simple' => ['nullable', 'integer', 'min:0'],
            'tarif_retour_simple' => ['nullable', 'integer', 'min:0'],
            'tarif_aller_retour' => ['nullable', 'integer', 'min:0'],
        ]);

        $trajet = $this->service->modifierTrajet($trajet, $donnees);

        return ApiResponse::success($this->resumer($trajet), 'Trajet mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->supprimerTrajet($this->trajet($id));

        return ApiResponse::success(null, 'Trajet supprimé.');
    }

    /** Alerte SMS aux parents des élèves du trajet (retard, incident, changement d'itinéraire). */
    public function notifier(Request $request, int $id): JsonResponse
    {
        $trajet = $this->trajet($id);

        $donnees = $request->validate([
            'type' => ['required', Rule::in(BusService::TYPES_NOTIFICATION)],
            'detail' => ['required', 'string', 'max:300'],
        ]);

        $envoyes = $this->service->notifierParents($trajet, $donnees['type'], $donnees['detail']);

        return ApiResponse::success(
            ['envoyes' => $envoyes],
            $envoyes > 0 ? "{$envoyes} parent(s) notifié(s)." : "Aucun parent n'a pu être notifié (numéro manquant).",
        );
    }

    // ---- Arrêts ---------------------------------------------------------

    public function ajouterArret(Request $request, int $trajetId): JsonResponse
    {
        $trajet = $this->trajet($trajetId);

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'ordre' => ['nullable', 'integer', 'min:1'],
            'heure_passage' => ['nullable', 'date_format:H:i'],
        ]);

        $arret = $this->service->ajouterArret($trajet, $donnees);

        return ApiResponse::created($this->resumerArret($arret), 'Arrêt ajouté.');
    }

    public function modifierArret(Request $request, int $trajetId, int $arretId): JsonResponse
    {
        $arret = $this->arret($trajetId, $arretId);

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'ordre' => ['nullable', 'integer', 'min:1'],
            'heure_passage' => ['nullable', 'date_format:H:i'],
        ]);

        $arret = $this->service->modifierArret($arret, $donnees);

        return ApiResponse::success($this->resumerArret($arret), 'Arrêt mis à jour.');
    }

    public function supprimerArret(int $trajetId, int $arretId): JsonResponse
    {
        $this->service->supprimerArret($this->arret($trajetId, $arretId));

        return ApiResponse::success(null, 'Arrêt supprimé.');
    }

    /** @return array<string, mixed> */
    private function resumer(BusTrajet $trajet): array
    {
        return [
            'id' => $trajet->id,
            'nom' => $trajet->nom,
            'description' => $trajet->description,
            'tarif_aller_simple' => $trajet->tarif_aller_simple,
            'tarif_retour_simple' => $trajet->tarif_retour_simple,
            'tarif_aller_retour' => $trajet->tarif_aller_retour,
            'vehicule' => $trajet->vehicule ? [
                'id' => $trajet->vehicule->id,
                'immatriculation' => $trajet->vehicule->immatriculation,
            ] : null,
            'arrets' => $trajet->arrets->map(fn (BusArret $a) => $this->resumerArret($a))->values(),
            'effectif' => $trajet->affectations_count ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function resumerDetail(BusTrajet $trajet): array
    {
        return [
            ...$this->resumer($trajet),
            'affectations' => $trajet->affectations->map(fn ($a) => [
                'id' => $a->id,
                'statut' => $a->statut,
                'tarif_mensuel' => $a->tarif_mensuel,
                'statut_paiement' => $a->statut_paiement,
                'option_trajet' => $a->option_trajet,
                'eleve' => [
                    'id' => $a->eleve->id,
                    'nom_complet' => $a->eleve->nom_complet,
                    'matricule' => $a->eleve->matricule,
                ],
                'arret' => $a->arret ? ['id' => $a->arret->id, 'nom' => $a->arret->nom] : null,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function resumerArret(BusArret $arret): array
    {
        return [
            'id' => $arret->id,
            'nom' => $arret->nom,
            'ordre' => $arret->ordre,
            'heure_passage' => $arret->heure_passage,
        ];
    }

    private function trajet(int $id): BusTrajet
    {
        return $this->service->trouverTrajet(app('tenant.school_id'), $id);
    }

    private function arret(int $trajetId, int $arretId): BusArret
    {
        return BusArret::where('trajet_id', $this->trajet($trajetId)->id)->findOrFail($arretId);
    }
}
