<?php

namespace Tests\Feature;

use App\Models\InventaireArticle;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * « Toutes les écoles » : un article partagé par tout le complexe.
 *
 * Un seul article, un seul stock — pas une copie par école. Les trois
 * établissements le voient dans leur inventaire et y puisent ensemble : ce
 * qu'une école sort, les autres ne l'ont plus.
 */
class InventaireToutesEcolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private School $nursery;

    private School $college;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->nursery = School::create(['name' => 'Nursery', 'code' => 'EBNS', 'type' => 'maternelle', 'is_active' => true]);
        School::create(['name' => 'Primary', 'code' => 'EBPS', 'type' => 'primaire', 'is_active' => true]);
        $this->college = School::create(['name' => 'College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->college->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /** @param array<string, mixed> $extra */
    private function creer(array $extra): \Illuminate\Testing\TestResponse
    {
        // Pas de X-School-Id : mode agrégé, celui où le choix a un sens.
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/inventaire', [
            'nom' => 'Paracétamol',
            'categorie' => 'autre',
            'quantite' => 100,
            'etat' => 'bon',
            'valeur_unitaire' => 100,
            ...$extra,
        ]);
    }

    public function test_l_article_partage_n_appartient_a_aucune_ecole(): void
    {
        $this->creer(['toutes_ecoles' => true])->assertCreated();

        $articles = InventaireArticle::where('nom', 'Paracétamol')->get();

        $this->assertCount(1, $articles);
        $this->assertNull($articles->first()->school_id);
    }

    /** Le même article se lit depuis n'importe laquelle des trois écoles. */
    public function test_chaque_ecole_voit_l_article_partage(): void
    {
        $this->creer(['toutes_ecoles' => true])->assertCreated();

        foreach ([$this->nursery, $this->college] as $ecole) {
            $this->actingAs($this->admin, 'sanctum')
                ->withHeader('X-School-Id', $ecole->id)
                ->getJson('/api/v1/inventaire')
                ->assertOk()
                ->assertJsonPath('data.articles.0.nom', 'Paracétamol');
        }
    }

    /** Un seul stock : ce qu'une école sort, les autres ne l'ont plus. */
    public function test_le_stock_partage_est_unique(): void
    {
        $this->creer(['toutes_ecoles' => true])->assertCreated();

        $article = InventaireArticle::where('nom', 'Paracétamol')->firstOrFail();
        $article->decrement('quantite', 30);

        $vueCollege = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->college->id)
            ->getJson('/api/v1/inventaire')
            ->assertOk();

        $this->assertSame(70, $vueCollege->json('data.articles.0.quantite'));
    }

    /** L'inventaire d'une école ne laisse pas fuiter celui d'une autre. */
    public function test_l_article_d_une_ecole_reste_invisible_aux_autres(): void
    {
        $this->creer(['school_id' => $this->nursery->id])->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->college->id)
            ->getJson('/api/v1/inventaire')
            ->assertOk()
            ->assertJsonCount(0, 'data.articles');
    }

    /** Le choix d'une école précise reste inchangé. */
    public function test_une_ecole_precise_rattache_l_article(): void
    {
        $this->creer(['school_id' => $this->nursery->id])->assertCreated();

        $article = InventaireArticle::where('nom', 'Paracétamol')->firstOrFail();

        $this->assertSame($this->nursery->id, $article->school_id);
    }
}
