<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BusRemplacement;
use App\Models\Personnel;
use App\Services\ChauffeurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Relais d'itinéraire vus par la direction : quel bus est sans chauffeur sur
 * quelle période, qui le reprend, et ce qui reste sans preneur.
 *
 * Pendant administratif de {@see ChauffeurEspaceController} : là où le
 * chauffeur ne peut confier que *son* bus et ne voit que les itinéraires
 * offerts, la direction ouvre un relais sur n'importe quel véhicule de la
 * flotte et peut désigner le remplaçant dans le même geste — un chauffeur
 * injoignable ne doit pas laisser un circuit sans bus.
 */
class BusRemplacementController extends Controller
{
    public function __construct(private readonly ChauffeurService $service) {}

    public function index(Request $request): JsonResponse
    {
        $donnees = $request->validate(['statut' => ['nullable', 'in:disponible,pourvu,annule']]);

        return ApiResponse::success($this->service->relaisDeLaFlotte($donnees['statut'] ?? null));
    }

    /**
     * Ouvre un relais sur un bus. Sans `chauffeur_remplacant_id`, l'itinéraire
     * est simplement rendu disponible et le premier chauffeur à le reprendre
     * l'emporte ; avec, il est attribué immédiatement.
     */
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'vehicule_id' => ['required', 'integer', 'exists:bus_vehicules,id'],
            'du' => ['required', 'date'],
            'au' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
            'chauffeur_remplacant_id' => ['nullable', 'integer', 'exists:personnels,id'],
        ]);

        try {
            // Pas de `$titulaire` : la direction n'est pas le chauffeur du bus,
            // le service reprend alors le titulaire enregistré sur le véhicule.
            $remplacement = $this->service->rendreDisponible($donnees, null, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created(
            $this->service->presenterRemplacement($remplacement),
            $remplacement->chauffeur_remplacant_id
                ? 'Itinéraire confié au chauffeur désigné.'
                : 'Itinéraire rendu disponible aux autres chauffeurs.',
        );
    }

    /** Désigne (ou remplace) le chauffeur qui reprend un itinéraire offert. */
    public function attribuer(Request $request, int $id): JsonResponse
    {
        $remplacement = BusRemplacement::findOrFail($id);

        $donnees = $request->validate([
            'chauffeur_remplacant_id' => ['required', 'integer', 'exists:personnels,id'],
        ]);

        try {
            $remplacement = $this->service->reprendre(
                $remplacement,
                Personnel::findOrFail($donnees['chauffeur_remplacant_id']),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->service->presenterRemplacement($remplacement),
            'Itinéraire confié au chauffeur désigné.',
        );
    }

    public function annuler(int $id): JsonResponse
    {
        $remplacement = BusRemplacement::findOrFail($id);

        try {
            $remplacement = $this->service->annulerRemplacement($remplacement);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->service->presenterRemplacement($remplacement),
            'Relais annulé : le bus revient à son chauffeur titulaire.',
        );
    }
}
