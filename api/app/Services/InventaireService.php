<?php

namespace App\Services;

use App\Models\InventaireArticle;
use App\Support\CodeBarreArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InventaireService extends BaseService
{
    /**
     * `$perPage` null renvoie le catalogue complet — utile aux étiquettes et
     * à l'export, qui doivent balayer tout le stock filtré plutôt qu'une page.
     *
     * @param  int|array<int>  $schoolId
     * @param  array{categorie?: ?string, etat?: ?string, search?: ?string}  $filtres
     */
    public function lister(int|array $schoolId, array $filtres = [], ?int $perPage = 30): Collection|LengthAwarePaginator
    {
        $detail = InventaireArticle::forSchool($schoolId)
            ->with('school:id,name,code,type')
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => $q->where('categorie', $c))
            ->when($filtres['etat'] ?? null, fn ($q, $e) => $q->where('etat', $e))
            ->when($filtres['search'] ?? null, fn ($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('nom', 'like', "%{$s}%")->orWhere('localisation', 'like', "%{$s}%");
            }))
            ->orderBy('nom');

        return $perPage !== null ? $detail->paginate($perPage) : $detail->get();
    }

    /** @param int|array<int> $schoolId */
    public function trouver(int|array $schoolId, int $id): InventaireArticle
    {
        return InventaireArticle::forSchool($schoolId)->findOrFail($id);
    }

    /**
     * `$schoolId` null crée un article partagé : un seul stock, commun aux
     * trois écoles, dans lequel chacune puise.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function creer(?int $schoolId, array $donnees): InventaireArticle
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
     * Attribue son code-barres à l'article, s'il n'en a pas déjà un.
     *
     * Idempotent à dessein : une étiquette est collée sur un objet physique.
     * Régénérer le code à chaque appel rendrait muettes toutes celles déjà en
     * rayon, et le comptoir ne saurait plus lire son propre stock.
     */
    public function attribuerCodeBarre(InventaireArticle $article): InventaireArticle
    {
        if ($article->code_barre !== null) {
            return $article;
        }

        $article->update(['code_barre' => CodeBarreArticle::pourArticle($article->id)]);

        return $article->refresh();
    }

    /**
     * Attribue leur code aux articles désignés et les rend tous — y compris
     * ceux qui en avaient déjà un, pour que l'appelant puisse imprimer la
     * planche d'étiquettes complète en une passe.
     *
     * @param  int|array<int>  $schoolId
     * @param  list<int>  $ids
     */
    public function attribuerCodesBarres(int|array $schoolId, array $ids): Collection
    {
        $articles = InventaireArticle::forSchool($schoolId)
            ->whereIn('id', $ids)
            ->with('school:id,name,code,type')
            ->orderBy('nom')
            ->get();

        foreach ($articles as $article) {
            $this->attribuerCodeBarre($article);
        }

        return $articles->fresh('school');
    }

    /**
     * Calculées en base plutôt que sur une collection chargée en mémoire :
     * le stock d'une école s'accumule sur des années sans jamais se purger,
     * contrairement à une grille tarifaire bornée par le nombre de classes.
     *
     * @param  int|array<int>  $schoolId
     * @return array{effectif_articles: int, quantite_totale: int, valeur_totale: int, par_etat: array<string, int>}
     */
    public function stats(int|array $schoolId): array
    {
        // Alias distinct du nom de l'accesseur `valeur_totale` du modèle : en
        // portant ce nom, la colonne agrégée serait masquée par
        // `getValeurTotaleAttribute()`, qui recalculerait (et renverrait 0)
        // à partir des attributs `quantite`/`valeur_unitaire` absents de cette
        // ligne d'agrégat plutôt que de lire la valeur SQL.
        $totaux = InventaireArticle::forSchool($schoolId)
            ->selectRaw('COUNT(*) as effectif, SUM(quantite) as quantite_totale, SUM(quantite * COALESCE(valeur_unitaire, 0)) as somme_valeur')
            ->first();

        $parEtat = InventaireArticle::forSchool($schoolId)
            ->selectRaw('etat, SUM(quantite) as quantite')
            ->groupBy('etat')
            ->pluck('quantite', 'etat');

        return [
            'effectif_articles' => (int) ($totaux->effectif ?? 0),
            'quantite_totale' => (int) ($totaux->quantite_totale ?? 0),
            'valeur_totale' => (int) ($totaux->somme_valeur ?? 0),
            'par_etat' => $parEtat->map(fn ($q) => (int) $q)->all(),
        ];
    }
}
