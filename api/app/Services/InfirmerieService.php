<?php

namespace App\Services;

use App\Models\InventaireArticle;
use App\Models\VisiteInfirmerie;
use App\Models\VisiteInfirmerieMateriel;
use Illuminate\Support\Facades\DB;

/**
 * Le matériel de l'inventaire consommé lors d'une visite doit décrémenter le
 * stock (sinon l'inventaire dérive silencieusement du réel), et une visite
 * modifiée ou supprimée doit rendre ce qu'elle avait pris — d'où la
 * transaction et le passage systématique par restituerStock() avant de
 * toucher aux lignes existantes.
 */
class InfirmerieService
{
    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<int>  $malaiseIds
     * @param  list<array{inventaire_article_id: int, quantite: int}>  $materiels
     */
    public function creer(array $donnees, array $malaiseIds, array $materiels): VisiteInfirmerie
    {
        return DB::transaction(function () use ($donnees, $malaiseIds, $materiels) {
            $visite = VisiteInfirmerie::create($donnees);
            $this->synchroniserMateriels($visite, $materiels);
            $visite->malaises()->sync($malaiseIds);
            $this->recalculerCout($visite);

            return $visite;
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<int>  $malaiseIds
     * @param  list<array{inventaire_article_id: int, quantite: int}>  $materiels
     */
    public function modifier(VisiteInfirmerie $visite, array $donnees, array $malaiseIds, array $materiels): VisiteInfirmerie
    {
        return DB::transaction(function () use ($visite, $donnees, $malaiseIds, $materiels) {
            $this->restituerStock($visite);
            $visite->materiels()->delete();

            $visite->update($donnees);
            $this->synchroniserMateriels($visite, $materiels);
            $visite->malaises()->sync($malaiseIds);
            $this->recalculerCout($visite);

            return $visite;
        });
    }

    public function supprimer(VisiteInfirmerie $visite): void
    {
        DB::transaction(function () use ($visite) {
            $this->restituerStock($visite);
            $visite->delete();
        });
    }

    /** @param  list<array{inventaire_article_id: int, quantite: int}>  $materiels */
    private function synchroniserMateriels(VisiteInfirmerie $visite, array $materiels): void
    {
        foreach ($materiels as $ligne) {
            $article = InventaireArticle::find($ligne['inventaire_article_id']);

            if (! $article) {
                continue;
            }

            $quantite = (int) $ligne['quantite'];
            $coutUnitaire = (int) ($article->valeur_unitaire ?? 0);

            VisiteInfirmerieMateriel::create([
                'visite_infirmerie_id' => $visite->id,
                'inventaire_article_id' => $article->id,
                'nom' => $article->nom,
                'quantite' => $quantite,
                'cout_unitaire' => $coutUnitaire,
                // Règle de trois : le prix d'une unité multiplié par la quantité prélevée.
                'cout' => $quantite * $coutUnitaire,
            ]);

            $article->decrement('quantite', min($quantite, $article->quantite));
        }
    }

    /** Remet en stock le matériel consommé par cette visite avant de la modifier ou de la supprimer. */
    private function restituerStock(VisiteInfirmerie $visite): void
    {
        $visite->materiels()->with('article')->get()->each(function (VisiteInfirmerieMateriel $ligne) {
            $ligne->article?->increment('quantite', $ligne->quantite);
        });
    }

    private function recalculerCout(VisiteInfirmerie $visite): void
    {
        $coutMateriels = (int) $visite->materiels()->sum('cout');

        $visite->update([
            'cout_materiels' => $coutMateriels,
            'cout_total' => (int) $visite->cout_soins + $coutMateriels + (int) $visite->cout_autre_materiel,
        ]);
    }
}
