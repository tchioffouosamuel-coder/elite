<?php

namespace App\Services;

use App\Models\InventaireArticle;
use Illuminate\Database\Eloquent\Collection;

class InventaireService extends BaseService
{
    /**
     * @param  int|array<int>  $schoolId
     * @param  array{categorie?: ?string, etat?: ?string, search?: ?string}  $filtres
     */
    public function lister(int|array $schoolId, array $filtres = []): Collection
    {
        return InventaireArticle::forSchool($schoolId)
            ->with('school:id,name,code,type')
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => $q->where('categorie', $c))
            ->when($filtres['etat'] ?? null, fn ($q, $e) => $q->where('etat', $e))
            ->when($filtres['search'] ?? null, fn ($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('nom', 'like', "%{$s}%")->orWhere('localisation', 'like', "%{$s}%");
            }))
            ->orderBy('nom')
            ->get();
    }

    /** @param int|array<int> $schoolId */
    public function trouver(int|array $schoolId, int $id): InventaireArticle
    {
        return InventaireArticle::forSchool($schoolId)->findOrFail($id);
    }

    /** @param array<string, mixed> $donnees */
    public function creer(int $schoolId, array $donnees): InventaireArticle
    {
        return InventaireArticle::create([...$donnees, 'school_id' => $schoolId]);
    }

    /** @param array<string, mixed> $donnees */
    public function modifier(InventaireArticle $article, array $donnees): InventaireArticle
    {
        $article->update($donnees);

        return $article->refresh();
    }

    public function supprimer(InventaireArticle $article): void
    {
        $article->delete();
    }

    /**
     * @param  int|array<int>  $schoolId
     * @return array{effectif_articles: int, quantite_totale: int, valeur_totale: int, par_etat: array<string, int>}
     */
    public function stats(int|array $schoolId): array
    {
        $articles = InventaireArticle::forSchool($schoolId)->get();

        return [
            'effectif_articles' => $articles->count(),
            'quantite_totale' => (int) $articles->sum('quantite'),
            'valeur_totale' => (int) $articles->sum(fn (InventaireArticle $a) => $a->valeur_totale),
            'par_etat' => $articles->groupBy('etat')->map(fn ($grp) => (int) $grp->sum('quantite'))->all(),
        ];
    }
}
