<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EleveResource;
use App\Models\BusRamassage;
use App\Models\BusRemplacement;
use App\Models\Personnel;
use App\Services\ChauffeurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Espace chauffeur : la tournée vue depuis le siège du conducteur — son bus,
 * les enfants qu'il transporte, la rentabilité du véhicule, et son itinéraire
 * arrêt par arrêt avec le pointage des enfants déjà pris.
 *
 * Périmètre « moi-même », comme {@see PersonnelEspaceController} : chaque
 * requête est bornée aux véhicules que le compte conduit effectivement ce
 * jour-là (cf. `ChauffeurService::vehicules`), jamais à la flotte que
 * `bus.view` ouvrirait. Aucune route d'écriture sur une souscription, un
 * trajet ou un arrêt : un chauffeur ne souscrit pas un élève et ne retouche
 * pas un circuit — il pointe sa tournée, et passe la main quand il est
 * empêché.
 */
class ChauffeurEspaceController extends Controller
{
    public function __construct(private readonly ChauffeurService $service) {}

    /**
     * Effectif transporté, rentabilité du mois, avancement de la tournée du
     * jour, et les itinéraires que des collègues empêchés ont rendus
     * disponibles.
     */
    public function tableauDeBord(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate([
            'mois' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
        ]);

        return ApiResponse::success($this->service->tableauDeBord(
            $chauffeur,
            $donnees['mois'] ?? null,
            $donnees['date'] ?? null,
        ));
    }

    /**
     * L'itinéraire : trajets du bus, arrêts dans l'ordre, et à chaque arrêt
     * les enfants qui y montent avec le contact de leurs tuteurs et l'état du
     * pointage.
     */
    public function itineraire(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate([
            'date' => ['nullable', 'date'],
            'sens' => ['nullable', 'in:' . implode(',', BusRamassage::SENS)],
        ]);

        return ApiResponse::success($this->service->itineraire(
            $chauffeur,
            $donnees['date'] ?? Carbon::today()->toDateString(),
            $donnees['sens'] ?? 'aller',
        ));
    }

    /** L'annuaire de bord : tous les enfants transportés et leurs contacts famille. */
    public function eleves(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate(['date' => ['nullable', 'date']]);

        return ApiResponse::success($this->service->elevesTransportes($chauffeur, $donnees['date'] ?? null));
    }

    public function profilEleve(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new EleveResource($this->service->profilEleve($this->moi($request), $id)));
    }

    public function depenses(Request $request): JsonResponse
    {
        $donnees = $request->validate(['mois' => ['nullable', 'date']]);

        return ApiResponse::success($this->service->depenses($this->moi($request), $donnees['mois'] ?? null));
    }

    /**
     * Coche (ou décoche) un enfant comme monté à son arrêt. Idempotent : le
     * geste peut être rejoué sans créer de doublon — utile sur une liaison
     * mobile qui coupe au milieu de la tournée.
     */
    public function pointer(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate([
            'affectation_id' => ['required', 'integer'],
            'date' => ['nullable', 'date'],
            'sens' => ['nullable', 'in:' . implode(',', BusRamassage::SENS)],
            'pris' => ['required', 'boolean'],
        ]);

        $ramassage = $this->service->pointer(
            $chauffeur,
            $donnees['affectation_id'],
            $donnees['date'] ?? Carbon::today()->toDateString(),
            $donnees['sens'] ?? 'aller',
            $donnees['pris'],
            $request->user()->id,
        );

        return ApiResponse::success([
            'affectation_id' => $donnees['affectation_id'],
            'pris' => $ramassage !== null,
            'pris_le' => $ramassage?->pris_le?->format('H:i'),
        ], $ramassage ? 'Élève pointé comme pris en charge.' : 'Pointage retiré.');
    }

    /** Itinéraires offerts par des collègues empêchés, que je peux reprendre. */
    public function itinerairesDisponibles(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate(['date' => ['nullable', 'date']]);

        return ApiResponse::success($this->service->itinerairesDisponibles($chauffeur, $donnees['date'] ?? null));
    }

    /**
     * Je suis empêché : mon itinéraire devient disponible sur la période, et
     * un collègue pourra le reprendre. Le bus doit être le mien — la
     * direction, elle, passe par {@see BusRemplacementController}.
     */
    public function declarerEmpechement(Request $request): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $donnees = $request->validate([
            'vehicule_id' => ['required', 'integer', 'exists:bus_vehicules,id'],
            'du' => ['required', 'date'],
            'au' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $remplacement = $this->service->rendreDisponible($donnees, $chauffeur, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created(
            $this->service->presenterRemplacement($remplacement),
            'Itinéraire rendu disponible : un autre chauffeur peut le reprendre.',
        );
    }

    /** Je reprends l'itinéraire d'un collègue empêché. */
    public function reprendre(Request $request, int $id): JsonResponse
    {
        $chauffeur = $this->moi($request);
        $remplacement = BusRemplacement::findOrFail($id);

        try {
            $remplacement = $this->service->reprendre($remplacement, $chauffeur);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->service->presenterRemplacement($remplacement),
            'Itinéraire repris : il apparaît désormais dans votre tournée.',
        );
    }

    /**
     * Mon empêchement est levé : je reprends mon bus. Seul le titulaire du
     * relais peut l'annuler ici ; la direction a sa propre route.
     */
    public function annulerEmpechement(Request $request, int $id): JsonResponse
    {
        $chauffeur = $this->moi($request);

        $remplacement = BusRemplacement::where('chauffeur_titulaire_id', $chauffeur->id)->findOrFail($id);

        try {
            $remplacement = $this->service->annulerRemplacement($remplacement);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->service->presenterRemplacement($remplacement),
            'Empêchement levé : votre itinéraire vous revient.',
        );
    }

    private function moi(Request $request): Personnel
    {
        $personnel = $request->user()->personnel;

        if (! $personnel) {
            throw new NotFoundHttpException('Aucune fiche personnel associée à ce compte.');
        }

        return $personnel;
    }
}
