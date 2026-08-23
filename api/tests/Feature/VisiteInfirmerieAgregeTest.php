<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\InventaireArticle;
use App\Models\MalaiseReferentiel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'infirmerie en mode agrégé — le cas réel du complexe Elites.
 *
 * Le super admin n'envoie pas d'en-tête X-School-Id : il voit les trois
 * écoles à la fois. L'élève soigné appartient à la maternelle, mais le compte
 * qui saisit est rattaché au collège. Rien dans la visite ne doit dépendre de
 * ce rattachement-là : c'est l'école de l'élève qui fait foi.
 */
class VisiteInfirmerieAgregeTest extends TestCase
{
    use RefreshDatabase;

    private Eleve $eleve;

    private User $admin;

    private MalaiseReferentiel $malaise;

    private InventaireArticle $article;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $maternelle = School::create([
            'name' => 'Elites Bilingual Nursery School', 'code' => 'EBNS',
            'type' => 'maternelle', 'is_active' => true,
        ]);

        $college = School::create([
            'name' => 'Elites Bilingual Technical College', 'code' => 'EBTC',
            'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $maternelle->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $maternelle->id, 'nom' => 'NURSERY 2-A',
        ]);

        $this->eleve = Eleve::create([
            'school_id' => $maternelle->id, 'classe_id' => $classe->id,
            'nom_complet' => 'Achu Favour-Bright Binui', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->malaise = MalaiseReferentiel::create([
            'school_id' => $maternelle->id, 'label_fr' => 'Maux de ventre',
        ]);

        $this->article = InventaireArticle::create([
            'school_id' => $maternelle->id, 'nom' => 'Paracétamol',
            'categorie' => 'autre', 'quantite' => 100, 'etat' => 'bon', 'valeur_unitaire' => 100,
        ]);

        // Le compte est rattaché au collège : c'est ce décalage qui faisait
        // échouer la validation.
        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $college->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /** @param array<string, mixed> $extra */
    private function enregistrer(array $extra = []): \Illuminate\Testing\TestResponse
    {
        // Pas de X-School-Id : mode agrégé, comme depuis l'écran « Santé ».
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/infirmerie/visites', [
            'eleve_id' => $this->eleve->id,
            'date_visite' => '2026-08-22T14:13',
            'raison' => 'Douleurs abdominales',
            'soins_prodiges' => 'Paracétamol + Albendazole',
            'type_traitement' => 'interne',
            'cout_soins' => 500,
            'autre_materiel' => 'Consultation',
            'cout_autre_materiel' => 100,
            'observations' => 'Doit suivre son traitement jusqu\'à sa fin.',
            ...$extra,
        ]);
    }

    public function test_une_visite_s_enregistre_pour_un_eleve_d_une_autre_ecole_que_celle_du_compte(): void
    {
        $this->enregistrer()
            ->assertCreated()
            ->assertJsonPath('data.eleve.nom_complet', 'Achu Favour-Bright Binui')
            ->assertJsonPath('data.classe.nom', 'NURSERY 2-A');
    }

    /** Les malaises sont ceux de l'école de l'élève, chargés avec son en-tête. */
    public function test_les_malaises_de_l_ecole_de_l_eleve_sont_acceptes(): void
    {
        $this->enregistrer(['malaise_ids' => [$this->malaise->id]])
            ->assertCreated()
            ->assertJsonPath('data.malaises.0.label_fr', 'Maux de ventre');
    }

    /** Idem pour l'inventaire : le stock prélevé est celui de l'école de l'élève. */
    public function test_le_materiel_de_l_ecole_de_l_eleve_est_accepte(): void
    {
        $this->enregistrer(['materiels' => [['inventaire_article_id' => $this->article->id, 'quantite' => 3]]])
            ->assertCreated()
            ->assertJsonPath('data.cout_materiels', 300)
            // 500 de soins + 300 de matériel + 100 d'autre matériel.
            ->assertJsonPath('data.cout_total', 900);

        $this->assertSame(97, $this->article->fresh()->quantite);
    }

    /** Le stock partagé par le complexe se prélève depuis n'importe quelle école. */
    public function test_le_materiel_partage_est_accepte(): void
    {
        $partage = InventaireArticle::create([
            'school_id' => null, 'nom' => 'Compresses',
            'categorie' => 'autre', 'quantite' => 50, 'etat' => 'bon', 'valeur_unitaire' => 200,
        ]);

        $this->enregistrer(['materiels' => [['inventaire_article_id' => $partage->id, 'quantite' => 2]]])
            ->assertCreated()
            ->assertJsonPath('data.cout_materiels', 400);

        $this->assertSame(48, $partage->fresh()->quantite);
    }

    /** Le cloisonnement reste : on ne prélève pas dans le stock d'une autre école. */
    public function test_le_materiel_d_une_autre_ecole_est_refuse(): void
    {
        $autre = InventaireArticle::create([
            'school_id' => $this->admin->school_id, 'nom' => 'Compresses',
            'categorie' => 'autre', 'quantite' => 50, 'etat' => 'bon', 'valeur_unitaire' => 200,
        ]);

        $this->enregistrer(['materiels' => [['inventaire_article_id' => $autre->id, 'quantite' => 1]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('materiels.0.inventaire_article_id');
    }
}
