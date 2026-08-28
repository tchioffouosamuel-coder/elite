<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\InventaireArticle;
use App\Models\School;
use App\Models\User;
use App\Models\VenteFourniture;
use App\Support\CataloguePermissions;
use App\Support\CodeBarreArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comptoir de vente des fournitures : sortie de stock facturée, réassort
 * valorisé, et le tout raccroché au journal comptable.
 */
class PointDeVenteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true,
        ]);

        AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-07-31',
            'is_active' => true,
        ]);

        // Les comptes que le service mouvemente : la migration les pose en
        // production, le test les recrée pour ne dépendre d'aucun seeder.
        foreach ([['571', 5, 'debit'], ['707', 7, 'credit'], ['607', 6, 'debit']] as [$code, $classe, $sens]) {
            CompteComptable::firstOrCreate(['code' => $code], [
                'libelle' => 'Compte '.$code, 'classe' => $classe, 'sens' => $sens, 'is_active' => true,
            ]);
        }

        $this->vendeur = User::create([
            'name' => 'Économe', 'email' => 'econome@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->vendeur->assignRole('super_admin');
    }

    private function article(array $attributs = []): InventaireArticle
    {
        return InventaireArticle::create([
            'school_id' => $this->school->id,
            'nom' => 'Cahier 200 pages',
            'categorie' => 'pedagogique',
            'quantite' => 50,
            'etat' => 'bon',
            'valeur_unitaire' => 400,
            'prix_vente' => 600,
            ...$attributs,
        ]);
    }

    // ---------------------------------------------------------- Code-barres

    public function test_generer_le_code_barre_pose_un_ean13_valide(): void
    {
        $article = $this->article(['code_barre' => null]);

        $reponse = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson("/api/v1/inventaire/{$article->id}/code-barre")
            ->assertOk();

        $code = $reponse->json('data.code_barre');

        $this->assertTrue(CodeBarreArticle::estValide($code), "{$code} n'est pas un EAN-13 valide.");
        $this->assertSame($article->id, CodeBarreArticle::articleId($code));
    }

    /** Une étiquette est collée sur un objet : régénérer le code la rendrait muette. */
    public function test_le_code_barre_ne_change_pas_a_la_seconde_generation(): void
    {
        $article = $this->article(['code_barre' => null]);

        $premier = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson("/api/v1/inventaire/{$article->id}/code-barre")->json('data.code_barre');
        $second = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson("/api/v1/inventaire/{$article->id}/code-barre")->json('data.code_barre');

        $this->assertSame($premier, $second);
    }

    public function test_le_comptoir_retrouve_un_article_par_son_code_barre(): void
    {
        $article = $this->article();
        $code = CodeBarreArticle::pourArticle($article->id);
        $article->update(['code_barre' => $code]);

        $this->actingAs($this->vendeur, 'sanctum')
            ->getJson("/api/v1/point-de-vente/articles/{$code}")
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.prix_vente', 600);
    }

    public function test_un_code_inconnu_rend_404_plutot_qu_une_liste_vide(): void
    {
        $this->actingAs($this->vendeur, 'sanctum')
            ->getJson('/api/v1/point-de-vente/articles/2000000009999')
            ->assertStatus(404);
    }

    /** Le catalogue ne propose que ce qui porte un prix : le mobilier reste de l'inventaire. */
    public function test_le_catalogue_ignore_les_articles_sans_prix_de_vente(): void
    {
        $this->article(['nom' => 'Cahier 200 pages']);
        $this->article(['nom' => 'Table-banc', 'prix_vente' => null]);

        $this->actingAs($this->vendeur, 'sanctum')
            ->getJson('/api/v1/point-de-vente/catalogue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Cahier 200 pages');
    }

    // --------------------------------------------------------------- Ventes

    public function test_une_vente_sort_le_stock_et_numerote_la_facture(): void
    {
        $article = $this->article();

        $reponse = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 3]],
                'mode' => 'especes',
            ])
            ->assertCreated()
            ->assertJsonPath('data.montant', 1800)
            ->assertJsonPath('data.cout', 1200)
            ->assertJsonPath('data.marge', 600);

        $this->assertSame('FV-ET-0001', $reponse->json('data.numero_facture'));
        $this->assertSame(47, $article->fresh()->quantite);
    }

    public function test_la_vente_ecrit_au_journal_comptable(): void
    {
        $article = $this->article();

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 2]],
            ])
            ->assertCreated();

        $ecritures = EcritureComptable::where('origine_type', (new VenteFourniture)->getMorphClass())->get();

        $this->assertCount(2, $ecritures);
        $this->assertSame(1200, (int) $ecritures->firstWhere('sens', 'debit')->montant);
        $this->assertSame(
            '707',
            CompteComptable::find($ecritures->firstWhere('sens', 'credit')->compte_comptable_id)->code,
        );
    }

    public function test_le_stock_insuffisant_bloque_la_vente_sans_rien_sortir(): void
    {
        $article = $this->article(['quantite' => 2]);

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 5]],
            ])
            ->assertStatus(422);

        $this->assertSame(2, $article->fresh()->quantite);
        $this->assertSame(0, VenteFourniture::count());
    }

    /** Le comptoir consent parfois un prix différent de l'affiche. */
    public function test_un_prix_saisi_au_comptoir_prime_sur_le_tarif_affiche(): void
    {
        $article = $this->article();

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 2, 'prix_unitaire' => 500]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.montant', 1000);
    }

    /**
     * Stats d'accueil vendeur : ses ventes du jour/mois et le stock vendable
     * — jamais les effectifs élèves/personnel (cf. DashboardService).
     */
    public function test_les_stats_vendeur_totalisent_ses_ventes_et_le_stock_vendable(): void
    {
        $article = $this->article();
        $this->article(['nom' => 'Sans prix', 'prix_vente' => null]);

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 3]],
            ])->assertCreated();

        $this->actingAs($this->vendeur, 'sanctum')
            ->getJson('/api/v1/point-de-vente/stats-vendeur')
            ->assertOk()
            ->assertJsonPath('data.ventes.jour.effectif', 1)
            ->assertJsonPath('data.ventes.jour.montant', 1800)
            ->assertJsonPath('data.ventes.mois.effectif', 1)
            ->assertJsonPath('data.ventes.mois.montant', 1800)
            // L'article sans prix de vente n'entre pas au comptoir : seul le
            // premier compte dans le stock vendable.
            ->assertJsonPath('data.stock.effectif_articles', 1)
            ->assertJsonPath('data.stock.quantite_totale', 47);
    }

    public function test_annuler_une_vente_remet_le_stock_et_contrepasse_les_ecritures(): void
    {
        $article = $this->article();

        $venteId = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 4]],
            ])->json('data.id');

        $this->assertSame(46, $article->fresh()->quantite);

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson("/api/v1/point-de-vente/ventes/{$venteId}/annuler", ['motif' => 'Erreur de saisie au comptoir'])
            ->assertOk()
            ->assertJsonPath('data.annule', true);

        $this->assertSame(50, $article->fresh()->quantite);

        // Deux écritures d'origine + deux contrepassations, de somme nulle.
        $ecritures = EcritureComptable::where('origine_type', (new VenteFourniture)->getMorphClass())->get();
        $this->assertCount(4, $ecritures);
        $this->assertSame(
            (int) $ecritures->where('sens', 'debit')->sum('montant'),
            (int) $ecritures->where('sens', 'credit')->sum('montant'),
        );
    }

    public function test_une_vente_annulee_ne_compte_plus_dans_les_totaux(): void
    {
        $article = $this->article();

        $venteId = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 1]],
            ])->json('data.id');

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson("/api/v1/point-de-vente/ventes/{$venteId}/annuler", ['motif' => 'Client revenu sur sa décision']);

        $this->actingAs($this->vendeur, 'sanctum')
            ->getJson('/api/v1/point-de-vente/ventes')
            ->assertOk()
            ->assertJsonPath('data.totaux.effectif', 0)
            ->assertJsonPath('data.totaux.montant', 0);
    }

    // ------------------------------------------------------ Entrées de stock

    public function test_une_entree_augmente_le_stock_et_repondere_le_cout(): void
    {
        // 50 cahiers à 400 F, puis 50 à 500 F : le coût moyen doit passer à 450.
        $article = $this->article(['quantite' => 50, 'valeur_unitaire' => 400]);

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/entrees', [
                'article_id' => $article->id,
                'quantite' => 50,
                'cout_unitaire' => 500,
                'fournisseur' => 'Librairie du Centre',
            ])
            ->assertCreated()
            ->assertJsonPath('data.cout_total', 25000);

        $article->refresh();
        $this->assertSame(100, $article->quantite);
        $this->assertSame(450, $article->valeur_unitaire);
    }

    /** Le module Dépenses journalise déjà les achats : pas de charge en double. */
    public function test_une_entree_ne_touche_pas_au_journal_par_defaut(): void
    {
        $article = $this->article();

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/entrees', [
                'article_id' => $article->id, 'quantite' => 10, 'cout_unitaire' => 400,
            ])
            ->assertCreated();

        $this->assertSame(0, EcritureComptable::count());
    }

    public function test_une_entree_comptabilisee_a_la_demande_ecrit_la_charge(): void
    {
        $article = $this->article();

        $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/entrees', [
                'article_id' => $article->id, 'quantite' => 10, 'cout_unitaire' => 400,
                'comptabiliser' => true,
            ])
            ->assertCreated();

        $ecritures = EcritureComptable::all();
        $this->assertCount(2, $ecritures);
        $this->assertSame(4000, (int) $ecritures->firstWhere('sens', 'debit')->montant);
        $this->assertSame(
            '607',
            CompteComptable::find($ecritures->firstWhere('sens', 'debit')->compte_comptable_id)->code,
        );
    }

    // -------------------------------------------------------------- Facture

    public function test_la_facture_s_edite_en_pdf(): void
    {
        $article = $this->article();

        $venteId = $this->actingAs($this->vendeur, 'sanctum')
            ->postJson('/api/v1/point-de-vente/ventes', [
                'lignes' => [['article_id' => $article->id, 'quantite' => 2]],
            ])->json('data.id');

        $reponse = $this->actingAs($this->vendeur, 'sanctum')
            ->get("/api/v1/point-de-vente/ventes/{$venteId}/facture")
            ->assertOk();

        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_la_planche_d_etiquettes_s_edite_en_pdf(): void
    {
        $article = $this->article(['code_barre' => null]);

        $reponse = $this->actingAs($this->vendeur, 'sanctum')
            ->post('/api/v1/inventaire/etiquettes', ['ids' => [$article->id], 'exemplaires' => 4])
            ->assertOk();

        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
        // L'impression attribue le code au passage : c'est le même geste.
        $this->assertNotNull($article->fresh()->code_barre);
    }
}
