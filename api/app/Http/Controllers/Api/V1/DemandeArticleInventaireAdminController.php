<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DemandeArticleInventaire;
use App\Services\DemandeArticleInventaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** File d'attente des demandes d'articles d'inventaire soumises par le personnel, à valider ou rejeter. */
class DemandeArticleInventaireAdminController extends Controller
{
    public function __construct(private readonly DemandeArticleInventaireService $service) {}

    public function index(Request $request): JsonResponse
    {
        $demandes = DemandeArticleInventaire::forSchool(Tenant::schoolIds())
            ->with(['personnel:id,nom_complet,matricule,fonction_id', 'inventaireArticle'])
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($demandes->map(fn (DemandeArticleInventaire $d) => $this->resume($d)));
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $demande = DemandeArticleInventaire::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $demande = $this->service->valider($demande, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($demande), 'Demande validée, article ajouté à l\'inventaire.');
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $demande = DemandeArticleInventaire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $demande = $this->service->rejeter($demande, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($demande), 'Demande rejetée.');
    }

    private function resume(DemandeArticleInventaire $d): array
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
            'donnees' => $d->donnees,
            'motif_rejet' => $d->motif_rejet,
            'inventaire_article_id' => $d->inventaire_article_id,
            'created_at' => $d->created_at->format('Y-m-d H:i'),
            'traite_le' => $d->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
