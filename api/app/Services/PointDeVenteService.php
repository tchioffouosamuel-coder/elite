<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\EntreeStock;
use App\Models\InventaireArticle;
use App\Models\School;
use App\Models\VenteFourniture;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Point de vente des fournitures scolaires.
 *
 * Le stock est celui de l'inventaire : pas de second magasin à tenir à jour.
 * Vendre décrémente `inventaire_articles.quantite`, réapprovisionner
 * l'incrémente — l'économe compte ses cahiers au même endroit qu'il compte ses
 * tables.
 *
 * Deux prix cohabitent sur un article et ne doivent jamais être confondus :
 * `valeur_unitaire` est le coût moyen pondéré d'acquisition, recalculé à chaque
 * entrée ; `prix_vente` est le tarif au comptoir. La marge, c'est leur écart,
 * figé ligne par ligne au moment de la vente.
 */
class PointDeVenteService extends BaseService
{
    private const TYPE_DOCUMENT = 'facture_vente';

    /** Compte de trésorerie mouvementé selon le moyen de paiement encaissé. */
    private const COMPTES_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    /** Produit des ventes de la boutique, distinct des frais de scolarité. */
    private const COMPTE_VENTES = '707';

    /** Charge du stock acheté pour être revendu. */
    private const COMPTE_ACHATS = '607';

    public function __construct(private readonly DocumentReferenceService $references) {}

    // ------------------------------------------------------------ Catalogue

    /**
     * Articles proposés au comptoir : ceux qui portent un prix de vente.
     *
     * @param  int|array<int>  $schoolId
     */
    public function catalogue(int|array $schoolId, ?string $recherche = null): Collection
    {
        return InventaireArticle::forSchool($schoolId)
            ->enVente()
            ->with('school:id,name,code,type')
            ->when($recherche, fn ($q, $s) => $q->where(fn ($query) => $query
                ->where('nom', 'like', "%{$s}%")
                ->orWhere('code_barre', $s)))
            ->orderBy('nom')
            ->get();
    }

    /**
     * Article désigné par une étiquette scannée.
     *
     * La douchette envoie les treize chiffres du code ; on interroge d'abord
     * la colonne, puis — si l'article a changé d'école ou que le code a été
     * saisi à la main — l'identifiant que le code porte lui-même.
     *
     * @param  int|array<int>  $schoolId
     */
    public function parCodeBarre(int|array $schoolId, string $code): ?InventaireArticle
    {
        return InventaireArticle::forSchool($schoolId)
            ->where('code_barre', trim($code))
            ->with('school:id,name,code,type')
            ->first();
    }

    // --------------------------------------------------------------- Ventes

    /**
     * Enregistre une vente : sortie du stock, facture numérotée, écritures.
     *
     * @param  array{lignes: list<array{article_id: int, quantite: int, prix_unitaire?: ?int}>, mode?: string, eleve_id?: ?int, client?: ?string, note?: ?string, date_vente?: ?string}  $donnees
     */
    public function vendre(int $schoolId, array $donnees, ?int $vendeurId = null): VenteFourniture
    {
        if ($donnees['lignes'] === []) {
            throw new RuntimeException('Une facture doit comporter au moins un article.');
        }

        return $this->transaction(function () use ($schoolId, $donnees, $vendeurId) {
            $school = School::findOrFail($schoolId);
            $annee = $this->anneeActive($schoolId);

            $vente = VenteFourniture::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $annee?->id,
                'numero_facture' => $this->numeroFacture($school, $annee?->id, $vendeurId),
                'date_vente' => $donnees['date_vente'] ?? now()->toDateString(),
                'montant' => 0,
                'mode' => $donnees['mode'] ?? 'especes',
                'eleve_id' => $donnees['eleve_id'] ?? null,
                'client' => $donnees['client'] ?? null,
                'vendu_par' => $vendeurId,
                'note' => $donnees['note'] ?? null,
            ]);

            $montant = 0;

            foreach ($donnees['lignes'] as $ligne) {
                // `lockForUpdate` : deux comptoirs qui vendent le dernier
                // cahier au même instant doivent se départager ici, pas
                // aboutir tous les deux à une quantité négative.
                $article = InventaireArticle::where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($ligne['article_id']);

                $quantite = (int) $ligne['quantite'];

                if ($quantite < 1) {
                    throw new RuntimeException("La quantité vendue de « {$article->nom} » doit être d'au moins 1.");
                }

                if ($article->quantite < $quantite) {
                    throw new RuntimeException(
                        "Stock insuffisant pour « {$article->nom} » : {$article->quantite} en rayon, {$quantite} demandé(s)."
                    );
                }

                if (! $article->estEnVente() && ! isset($ligne['prix_unitaire'])) {
                    throw new RuntimeException("« {$article->nom} » n'a pas de prix de vente : fixez-le dans l'inventaire.");
                }

                $prix = (int) ($ligne['prix_unitaire'] ?? $article->prix_vente);

                $vente->lignes()->create([
                    'inventaire_article_id' => $article->id,
                    'libelle' => $article->nom,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prix,
                    'cout_unitaire' => $article->valeur_unitaire,
                ]);

                $article->decrement('quantite', $quantite);
                $montant += $prix * $quantite;
            }

            $vente->update(['montant' => $montant]);
            $this->comptabiliserVente($vente->fresh('lignes'));

            return $vente->fresh(['lignes', 'eleve', 'vendeur', 'school']);
        });
    }

    /**
     * Annule une vente : le stock revient en rayon et les écritures sont
     * contrepassées. La facture, elle, reste au registre — son numéro a été
     * remis au public.
     */
    public function annuler(VenteFourniture $vente, string $motif, ?int $annulePar = null): VenteFourniture
    {
        if ($vente->estAnnulee()) {
            throw new RuntimeException('Cette vente est déjà annulée.');
        }

        return $this->transaction(function () use ($vente, $motif, $annulePar) {
            foreach ($vente->lignes as $ligne) {
                $ligne->article?->increment('quantite', $ligne->quantite);
            }

            $vente->update([
                'annule_le' => now(),
                'annule_par' => $annulePar,
                'motif_annulation' => $motif,
            ]);

            foreach ($vente->ecritures as $ecriture) {
                EcritureComptable::create([
                    'school_id' => $ecriture->school_id,
                    'annee_scolaire_id' => $ecriture->annee_scolaire_id,
                    'date_ecriture' => now()->toDateString(),
                    'libelle' => 'Annulation — '.$ecriture->libelle,
                    'montant' => $ecriture->montant,
                    'sens' => $ecriture->sens === 'debit' ? 'credit' : 'debit',
                    'compte_comptable_id' => $ecriture->compte_comptable_id,
                    'origine_type' => $vente->getMorphClass(),
                    'origine_id' => $vente->id,
                ]);
            }

            return $vente->fresh(['lignes', 'eleve', 'vendeur', 'school']);
        });
    }

    /**
     * Trésorerie au débit, produit des ventes au crédit : l'argent entre en
     * caisse en contrepartie d'une recette de la boutique.
     */
    private function comptabiliserVente(VenteFourniture $vente): void
    {
        $commun = [
            'school_id' => $vente->school_id,
            'annee_scolaire_id' => $vente->annee_scolaire_id,
            'date_ecriture' => $vente->date_vente,
            'montant' => $vente->montant,
            'origine_type' => $vente->getMorphClass(),
            'origine_id' => $vente->id,
        ];

        EcritureComptable::create($commun + [
            'libelle' => 'Vente de fournitures — facture '.$vente->numero_facture,
            'sens' => 'debit',
            'compte_comptable_id' => $this->compte(self::COMPTES_TRESORERIE[$vente->mode] ?? '571'),
        ]);

        EcritureComptable::create($commun + [
            'libelle' => 'Produit boutique — facture '.$vente->numero_facture,
            'sens' => 'credit',
            'compte_comptable_id' => $this->compte(self::COMPTE_VENTES),
        ]);
    }

    // ------------------------------------------------------ Entrées de stock

    /**
     * Réapprovisionne un article et recalcule son coût unitaire moyen pondéré.
     *
     * `comptabiliser` est volontairement optionnel et faux par défaut : le
     * module Dépenses journalise déjà les achats de l'établissement. Cocher les
     * deux pour une même facture fournisseur compterait la charge en double.
     *
     * @param  array{article_id: int, quantite: int, cout_unitaire: int, fournisseur?: ?string, reference?: ?string, note?: ?string, date_entree?: ?string, comptabiliser?: bool}  $donnees
     */
    public function entrerStock(int $schoolId, array $donnees, ?int $parUserId = null): EntreeStock
    {
        return $this->transaction(function () use ($schoolId, $donnees, $parUserId) {
            $article = InventaireArticle::where('school_id', $schoolId)
                ->lockForUpdate()
                ->findOrFail($donnees['article_id']);

            $quantite = (int) $donnees['quantite'];
            $coutUnitaire = (int) $donnees['cout_unitaire'];

            if ($quantite < 1) {
                throw new RuntimeException("La quantité entrée doit être d'au moins 1.");
            }

            $annee = $this->anneeActive($schoolId);

            $entree = EntreeStock::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $annee?->id,
                'inventaire_article_id' => $article->id,
                'date_entree' => $donnees['date_entree'] ?? now()->toDateString(),
                'quantite' => $quantite,
                'cout_unitaire' => $coutUnitaire,
                'fournisseur' => $donnees['fournisseur'] ?? null,
                'reference' => $donnees['reference'] ?? null,
                'enregistre_par' => $parUserId,
                'note' => $donnees['note'] ?? null,
            ]);

            $article->update([
                'quantite' => $article->quantite + $quantite,
                'valeur_unitaire' => $this->coutMoyenPondere($article, $quantite, $coutUnitaire),
            ]);

            if ($donnees['comptabiliser'] ?? false) {
                $this->comptabiliserEntree($entree->fresh(), $article->nom);
            }

            return $entree->fresh(['article', 'enregistreur']);
        });
    }

    /**
     * Coût moyen pondéré après entrée. Le stock déjà en rayon est valorisé à
     * l'ancien coût, le lot qui arrive au sien : la moyenne suit le prix réel
     * payé plutôt que d'écraser l'historique au dernier tarif du fournisseur.
     */
    private function coutMoyenPondere(InventaireArticle $article, int $quantiteEntree, int $coutEntree): int
    {
        $quantiteExistante = max(0, $article->quantite);
        $coutExistant = (int) $article->valeur_unitaire;
        $total = $quantiteExistante + $quantiteEntree;

        if ($total === 0) {
            return $coutEntree;
        }

        return (int) round(($quantiteExistante * $coutExistant + $quantiteEntree * $coutEntree) / $total);
    }

    /** Charge au débit, trésorerie au crédit : l'école paie son réassort. */
    private function comptabiliserEntree(EntreeStock $entree, string $nomArticle): void
    {
        $commun = [
            'school_id' => $entree->school_id,
            'annee_scolaire_id' => $entree->annee_scolaire_id,
            'date_ecriture' => $entree->date_entree,
            'montant' => $entree->cout_total,
            'origine_type' => $entree->getMorphClass(),
            'origine_id' => $entree->id,
        ];

        EcritureComptable::create($commun + [
            'libelle' => 'Réassort boutique — '.$nomArticle,
            'sens' => 'debit',
            'compte_comptable_id' => $this->compte(self::COMPTE_ACHATS),
        ]);

        EcritureComptable::create($commun + [
            'libelle' => 'Règlement réassort — '.$nomArticle,
            'sens' => 'credit',
            'compte_comptable_id' => $this->compte('571'),
        ]);
    }

    // --------------------------------------------------------------- Lecture

    /**
     * Journal des ventes sur une période, avec ses totaux.
     *
     * @param  int|array<int>  $schoolId
     * @param  array{du?: ?string, au?: ?string, eleve_id?: ?int, annulees?: bool}  $filtres
     * @return array{ventes: Collection, totaux: array{effectif: int, montant: int, cout: int, marge: int}}
     */
    /**
     * Stats du tableau de bord vendeur : ses propres ventes (jour, mois) et
     * l'état du stock vendable — jamais les effectifs élèves/personnel, hors
     * de son périmètre métier (cf. écran d'accueil vendeur, mobile et web).
     *
     * @param  int|array<int>  $schoolId
     */
    public function statsVendeur(int|array $schoolId, int $vendeurId): array
    {
        $ventes = VenteFourniture::forSchool($schoolId)->where('vendu_par', $vendeurId)->valides();

        $jourVentes = (clone $ventes)->whereDate('date_vente', today())->get();
        $moisVentes = (clone $ventes)->whereMonth('date_vente', today()->month)->whereYear('date_vente', today()->year)->get();

        $articles = InventaireArticle::forSchool($schoolId)->enVente()->get();

        return [
            'ventes' => [
                'jour' => ['effectif' => $jourVentes->count(), 'montant' => (int) $jourVentes->sum('montant')],
                'mois' => ['effectif' => $moisVentes->count(), 'montant' => (int) $moisVentes->sum('montant')],
            ],
            'stock' => [
                'effectif_articles' => $articles->count(),
                'quantite_totale' => (int) $articles->sum('quantite'),
                'valeur_totale' => (int) $articles->sum(fn (InventaireArticle $a) => $a->valeur_vente),
            ],
        ];
    }

    public function journal(int|array $schoolId, array $filtres = []): array
    {
        $ventes = VenteFourniture::forSchool($schoolId)
            ->with(['lignes', 'eleve:id,nom_complet,matricule', 'vendeur:id,name', 'school:id,name,code,type'])
            ->when($filtres['du'] ?? null, fn ($q, $d) => $q->whereDate('date_vente', '>=', $d))
            ->when($filtres['au'] ?? null, fn ($q, $d) => $q->whereDate('date_vente', '<=', $d))
            ->when($filtres['eleve_id'] ?? null, fn ($q, $id) => $q->where('eleve_id', $id))
            ->when(! ($filtres['annulees'] ?? false), fn ($q) => $q->valides())
            ->orderByDesc('date_vente')
            ->orderByDesc('id')
            ->get();

        $valides = $ventes->reject(fn (VenteFourniture $v) => $v->estAnnulee());
        $montant = (int) $valides->sum('montant');
        $cout = (int) $valides->sum(fn (VenteFourniture $v) => $v->cout);

        return [
            'ventes' => $ventes,
            'totaux' => [
                'effectif' => $valides->count(),
                'montant' => $montant,
                'cout' => $cout,
                'marge' => $montant - $cout,
            ],
        ];
    }

    /**
     * @param  int|array<int>  $schoolId
     * @param  array{du?: ?string, au?: ?string, article_id?: ?int}  $filtres
     * @return array{entrees: Collection, totaux: array{effectif: int, quantite: int, cout: int}}
     */
    public function entrees(int|array $schoolId, array $filtres = []): array
    {
        $entrees = EntreeStock::forSchool($schoolId)
            ->with(['article:id,nom,code_barre,quantite', 'enregistreur:id,name', 'school:id,name,code,type'])
            ->when($filtres['du'] ?? null, fn ($q, $d) => $q->whereDate('date_entree', '>=', $d))
            ->when($filtres['au'] ?? null, fn ($q, $d) => $q->whereDate('date_entree', '<=', $d))
            ->when($filtres['article_id'] ?? null, fn ($q, $id) => $q->where('inventaire_article_id', $id))
            ->orderByDesc('date_entree')
            ->orderByDesc('id')
            ->get();

        return [
            'entrees' => $entrees,
            'totaux' => [
                'effectif' => $entrees->count(),
                'quantite' => (int) $entrees->sum('quantite'),
                'cout' => (int) $entrees->sum(fn (EntreeStock $e) => $e->cout_total),
            ],
        ];
    }

    public function trouverVente(int|array $schoolId, int $id): VenteFourniture
    {
        return VenteFourniture::forSchool($schoolId)
            ->with(['lignes', 'eleve:id,nom_complet,matricule', 'vendeur:id,name', 'school'])
            ->findOrFail($id);
    }

    // ----------------------------------------------------------- Utilitaires

    private function numeroFacture(School $school, ?int $anneeScolaireId, ?int $generePar): string
    {
        $reference = $this->references->attribuer($school->id, self::TYPE_DOCUMENT, $anneeScolaireId, null, $generePar);

        return sprintf('FV-%s-%s', $school->code, str_pad((string) $reference->numero, 4, '0', STR_PAD_LEFT));
    }

    private function anneeActive(int $schoolId): ?AnneeScolaire
    {
        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
    }

    private function compte(string $code): ?int
    {
        return CompteComptable::where('code', $code)->value('id');
    }
}
