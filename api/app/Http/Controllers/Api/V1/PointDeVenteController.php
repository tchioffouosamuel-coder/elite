<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EntreeStock;
use App\Models\InventaireArticle;
use App\Models\VenteFourniture;
use App\Services\PointDeVenteService;
use App\Support\Pdf\FactureVenteGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comptoir de vente des fournitures scolaires.
 *
 * Le catalogue et le stock viennent de l'inventaire : ce contrôleur n'expose
 * pas un second magasin, il ouvre la caisse par-dessus celui qui existe.
 */
class PointDeVenteController extends Controller
{
    public function __construct(private readonly PointDeVenteService $service) {}

    /** Articles proposés au comptoir — ceux qui portent un prix de vente. */
    public function catalogue(Request $request): JsonResponse
    {
        $articles = $this->service->catalogue(
            Tenant::schoolIds(),
            $request->string('search')->toString() ?: null,
        );

        return ApiResponse::success(
            $articles->map(fn (InventaireArticle $a) => $this->resumerArticle($a))->values()
        );
    }

    /**
     * Article désigné par une étiquette scannée. Rend 404 plutôt qu'une liste
     * vide : au comptoir, « code inconnu » est une information, pas un résultat.
     */
    public function parCodeBarre(string $code): JsonResponse
    {
        $article = $this->service->parCodeBarre(Tenant::schoolIds(), $code);

        if ($article === null) {
            return ApiResponse::error("Aucun article ne porte le code-barres {$code}.", 404);
        }

        return ApiResponse::success($this->resumerArticle($article));
    }

    // --------------------------------------------------------------- Ventes

    public function ventes(Request $request): JsonResponse
    {
        $resultat = $this->service->journal(Tenant::schoolIds(), [
            'du' => $request->string('du')->toString() ?: null,
            'au' => $request->string('au')->toString() ?: null,
            'eleve_id' => $request->integer('eleve_id') ?: null,
            'annulees' => $request->boolean('annulees'),
        ]);

        return ApiResponse::success([
            'ventes' => $resultat['ventes']->map(fn (VenteFourniture $v) => $this->resumerVente($v))->values(),
            'totaux' => $resultat['totaux'],
        ]);
    }

    /** Stats de l'écran d'accueil vendeur : ses ventes (jour/mois) et le stock vendable. */
    public function statsVendeur(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->statsVendeur(Tenant::schoolIds(), $request->user()->id)
        );
    }

    public function vendre(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.article_id' => ['required', 'integer', 'exists:inventaire_articles,id'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
            // Laissé libre : le comptoir consent parfois un prix différent de
            // l'affiche (lot, remise). Absent, le prix de l'article s'applique.
            'lignes.*.prix_unitaire' => ['nullable', 'integer', 'min:0'],
            'mode' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'eleve_id' => ['nullable', 'integer', 'exists:eleves,id'],
            'client' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'date_vente' => ['nullable', 'date'],
        ]);

        /*
         * L'école se déduit des articles vendus : le comptoir ne doit pas avoir
         * à la désigner — un super admin en mode « Toutes les écoles » serait
         * sinon bloqué au moment d'encaisser, le pire endroit pour poser une
         * question.
         *
         * Les articles partagés ne désignent aucune école : ils se glissent
         * dans n'importe quelle facture sans la contraindre, et `filter()` les
         * écarte du raisonnement. Une facture qui n'en contient que ceux-là
         * n'apprend donc rien sur son école — d'où le repli sur le périmètre
         * courant, qui redemandera de choisir en mode agrégé.
         */
        $ecoles = InventaireArticle::whereIn('id', collect($donnees['lignes'])->pluck('article_id')->unique())
            ->pluck('school_id')
            ->filter()
            ->unique();

        if ($ecoles->count() > 1) {
            return ApiResponse::error('Une facture ne peut pas mélanger les articles de plusieurs écoles.', 422);
        }

        $schoolId = Tenant::resolveWriteSchoolId($donnees['school_id'] ?? $ecoles->first());
        unset($donnees['school_id']);

        try {
            $vente = $this->service->vendre($schoolId, $donnees, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resumerVente($vente), 'Vente enregistrée — facture '.$vente->numero_facture.'.');
    }

    public function annulerVente(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);
        $vente = $this->service->trouverVente(Tenant::schoolIds(), $id);

        try {
            $vente = $this->service->annuler($vente, $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resumerVente($vente), 'Vente annulée, stock rétabli.');
    }

    public function facture(int $id): Response
    {
        $vente = $this->service->trouverVente(Tenant::schoolIds(), $id);
        $pdf = (new FactureVenteGenerator)->build($vente);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="facture-'.$vente->numero_facture.'.pdf"',
        ]);
    }

    // ------------------------------------------------------ Entrées de stock

    public function entrees(Request $request): JsonResponse
    {
        $resultat = $this->service->entrees(Tenant::schoolIds(), [
            'du' => $request->string('du')->toString() ?: null,
            'au' => $request->string('au')->toString() ?: null,
            'article_id' => $request->integer('article_id') ?: null,
        ]);

        return ApiResponse::success([
            'entrees' => $resultat['entrees']->map(fn (EntreeStock $e) => $this->resumerEntree($e))->values(),
            'totaux' => $resultat['totaux'],
        ]);
    }

    public function entrer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'article_id' => ['required', 'integer', 'exists:inventaire_articles,id'],
            'quantite' => ['required', 'integer', 'min:1'],
            'cout_unitaire' => ['required', 'integer', 'min:0'],
            'fournisseur' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'date_entree' => ['nullable', 'date'],
            // Le module Dépenses journalise déjà les achats : on ne comptabilise
            // le réassort que si l'économe le demande explicitement, sinon la
            // charge serait comptée deux fois.
            'comptabiliser' => ['nullable', 'boolean'],
        ]);

        // Même principe qu'à la vente : le réassort suit l'école de l'article,
        // et un article partagé n'en désigne aucune — il faut alors préciser
        // sur quel budget l'achat est imputé.
        $schoolId = Tenant::resolveWriteSchoolId(
            $donnees['school_id'] ?? InventaireArticle::whereKey($donnees['article_id'])->value('school_id'),
        );
        unset($donnees['school_id']);

        try {
            $entree = $this->service->entrerStock($schoolId, $donnees, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resumerEntree($entree), 'Entrée de stock enregistrée.');
    }

    // ------------------------------------------------------------- Résumés

    /** @return array<string, mixed> */
    private function resumerArticle(InventaireArticle $article): array
    {
        return [
            'id' => $article->id,
            'nom' => $article->nom,
            'code_barre' => $article->code_barre,
            'categorie' => $article->categorie,
            'quantite' => $article->quantite,
            'prix_vente' => $article->prix_vente,
            'valeur_unitaire' => $article->valeur_unitaire,
            'school' => $article->school ? [
                'id' => $article->school->id,
                'name' => $article->school->name,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function resumerVente(VenteFourniture $vente): array
    {
        return [
            'id' => $vente->id,
            'numero_facture' => $vente->numero_facture,
            'date_vente' => $vente->date_vente?->format('Y-m-d'),
            'montant' => $vente->montant,
            'cout' => $vente->cout,
            'marge' => $vente->marge,
            'mode' => $vente->mode,
            'client' => $vente->client,
            'note' => $vente->note,
            'annule' => $vente->estAnnulee(),
            'motif_annulation' => $vente->motif_annulation,
            'eleve' => $vente->eleve ? [
                'id' => $vente->eleve->id,
                'nom_complet' => $vente->eleve->nom_complet,
                'matricule' => $vente->eleve->matricule,
            ] : null,
            'vendeur' => $vente->vendeur?->name,
            'school' => $vente->school ? [
                'id' => $vente->school->id,
                'name' => $vente->school->name,
            ] : null,
            'lignes' => $vente->lignes->map(fn ($ligne) => [
                'id' => $ligne->id,
                'article_id' => $ligne->inventaire_article_id,
                'libelle' => $ligne->libelle,
                'quantite' => $ligne->quantite,
                'prix_unitaire' => $ligne->prix_unitaire,
                'cout_unitaire' => $ligne->cout_unitaire,
                'total' => $ligne->total,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function resumerEntree(EntreeStock $entree): array
    {
        return [
            'id' => $entree->id,
            'date_entree' => $entree->date_entree?->format('Y-m-d'),
            'quantite' => $entree->quantite,
            'cout_unitaire' => $entree->cout_unitaire,
            'cout_total' => $entree->cout_total,
            'fournisseur' => $entree->fournisseur,
            'reference' => $entree->reference,
            'note' => $entree->note,
            'enregistre_par' => $entree->enregistreur?->name,
            'article' => $entree->article ? [
                'id' => $entree->article->id,
                'nom' => $entree->article->nom,
                'code_barre' => $entree->article->code_barre,
                'quantite' => $entree->article->quantite,
            ] : null,
            'school' => $entree->school ? [
                'id' => $entree->school->id,
                'name' => $entree->school->name,
            ] : null,
        ];
    }
}
