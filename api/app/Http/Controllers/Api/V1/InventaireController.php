<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventaireArticle;
use App\Services\InventaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventaireController extends Controller
{
    public function __construct(private readonly InventaireService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = [
            'categorie' => $request->string('categorie')->toString() ?: null,
            'etat' => $request->string('etat')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ];

        $articles = $this->service->lister(app('tenant.school_id'), $filtres);

        return ApiResponse::success([
            'articles' => $articles->map(fn (InventaireArticle $a) => $this->resumer($a))->values(),
            'stats' => $this->service->stats(app('tenant.school_id')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);

        $article = $this->service->creer(app('tenant.school_id'), $donnees);

        return ApiResponse::created($this->resumer($article), 'Article ajouté.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $article = $this->service->trouver(app('tenant.school_id'), $id);
        $donnees = $this->valider($request);

        $article = $this->service->modifier($article, $donnees);

        return ApiResponse::success($this->resumer($article), 'Article mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $article = $this->service->trouver(app('tenant.school_id'), $id);
        $this->service->supprimer($article);

        return ApiResponse::success(null, 'Article supprimé.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'categorie' => ['required', 'in:mobilier,informatique,pedagogique,sport,autre'],
            'quantite' => ['required', 'integer', 'min:1'],
            'etat' => ['required', 'in:bon,moyen,mauvais,hors_service'],
            'localisation' => ['nullable', 'string', 'max:150'],
            'valeur_unitaire' => ['nullable', 'integer', 'min:0'],
            'date_acquisition' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(InventaireArticle $article): array
    {
        return [
            'id' => $article->id,
            'nom' => $article->nom,
            'categorie' => $article->categorie,
            'quantite' => $article->quantite,
            'etat' => $article->etat,
            'localisation' => $article->localisation,
            'valeur_unitaire' => $article->valeur_unitaire,
            'valeur_totale' => $article->valeur_totale,
            'date_acquisition' => $article->date_acquisition?->format('Y-m-d'),
            'notes' => $article->notes,
        ];
    }
}
