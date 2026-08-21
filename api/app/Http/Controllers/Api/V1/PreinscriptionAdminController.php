<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Preinscription;
use App\Services\PreinscriptionService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** File d'attente des préinscriptions déposées par les parents, à valider ou rejeter. */
class PreinscriptionAdminController extends Controller
{
    public function __construct(private readonly PreinscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $preinscriptions = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule'])
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($preinscriptions->map(fn (Preinscription $p) => $this->resume($p)));
    }

    public function show(int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule,classe_id', 'eleve.classe:id,nom'])
            ->findOrFail($id);

        return ApiResponse::success([
            ...$this->resume($p),
            'donnees_eleve' => $p->donnees_eleve,
            'donnees_tuteurs' => $p->donnees_tuteurs,
            'note_admin' => $p->note_admin,
            'mode_versement' => $p->mode_versement,
            'reference_externe' => $p->reference_externe,
            'rubriques_versement' => $p->rubriques_versement,
            'classe_actuelle' => $p->eleve?->classe?->nom,
        ]);
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $p = $this->service->valider($p, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p->load('eleve:id,nom_complet,matricule')), 'Préinscription validée.');
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $p = $this->service->rejeter($p, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p), 'Préinscription rejetée.');
    }

    private function resume(Preinscription $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'statut' => $p->statut,
            'tuteur' => $p->tuteur ? ['id' => $p->tuteur->id, 'nom_complet' => $p->tuteur->nom_complet, 'telephone' => $p->tuteur->telephone, 'email' => $p->tuteur->email] : null,
            'eleve' => $p->eleve ? ['id' => $p->eleve->id, 'nom_complet' => $p->eleve->nom_complet, 'matricule' => $p->eleve->matricule] : null,
            'nom_propose' => $p->donnees_eleve['nom_complet'] ?? null,
            'montant_verser' => $p->montant_verser,
            'motif_rejet' => $p->motif_rejet,
            'created_at' => $p->created_at->format('Y-m-d H:i'),
            'traite_le' => $p->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
