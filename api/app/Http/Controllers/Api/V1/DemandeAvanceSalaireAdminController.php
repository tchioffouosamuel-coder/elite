<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DemandeAvanceSalaire;
use App\Services\AvanceSalaireService;
use App\Services\DemandeAvanceSalaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** File d'attente des demandes d'avance sur salaire soumises par le personnel, à valider ou rejeter. */
class DemandeAvanceSalaireAdminController extends Controller
{
    public function __construct(
        private readonly DemandeAvanceSalaireService $service,
        private readonly AvanceSalaireService $avances,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demandes = DemandeAvanceSalaire::forSchool(Tenant::schoolIds())
            ->with('personnel:id,nom_complet,matricule,fonction_id')
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($demandes->map(fn (DemandeAvanceSalaire $d) => $this->resume($d)));
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $demande = DemandeAvanceSalaire::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $demande = $this->service->valider($demande, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($demande), 'Demande validée, avance accordée.');
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $demande = DemandeAvanceSalaire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $demande = $this->service->rejeter($demande, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($demande), 'Demande rejetée.');
    }

    private function resume(DemandeAvanceSalaire $d): array
    {
        return [
            'id' => $d->id,
            'statut' => $d->statut,
            'personnel' => $d->personnel ? [
                'id' => $d->personnel->id,
                'nom_complet' => $d->personnel->nom_complet,
                'matricule' => $d->personnel->matricule,
                'fonction' => $d->personnel->fonction,
            ] : null,
            'montant' => $d->montant,
            'nombre_mois' => $d->nombre_mois,
            // Échéancier tel qu'il sera appliqué à la validation, avec la
            // borne des 50% : de quoi trancher sans ouvrir la fiche de paie.
            'mensualite' => $this->avances->calculerMensualite($d->montant, max(1, $d->nombre_mois)),
            'plafond_mensualite' => $d->personnel ? ($this->avances->plafond($d->personnel)['plafond_mensualite'] ?? null) : null,
            'motif' => $d->motif,
            'motif_rejet' => $d->motif_rejet,
            'avance_salaire_id' => $d->avance_salaire_id,
            'created_at' => $d->created_at->format('Y-m-d H:i'),
            'traite_le' => $d->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
