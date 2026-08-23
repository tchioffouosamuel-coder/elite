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

class VisiteInfirmerieTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_passage_a_l_infirmerie_est_cree_modifie_puis_supprime(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'Elites Test',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $school->id,
            'nom' => '6e A',
        ]);

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/infirmerie/visites', [
                'eleve_id' => $eleve->id,
                'date_visite' => '2026-08-17T10:35',
                'raison' => 'Maux de tête',
                'soins_prodiges' => 'Repos et prise de température',
                'type_traitement' => 'interne',
                'cout_soins' => 500,
                'observations' => 'Parent informé.',
            ]);

        $creation->assertCreated()
            ->assertJsonPath('data.eleve.nom_complet', 'Alice Ngono')
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.cout_soins', 500)
            ->assertJsonPath('data.cout_total', 500);

        $id = $creation->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/infirmerie/visites/{$id}", [
                'eleve_id' => $eleve->id,
                'date_visite' => '2026-08-17T11:00',
                'raison' => 'Maux de tête persistants',
                'soins_prodiges' => 'Repos prolongé',
                'type_traitement' => 'interne',
                'cout_soins' => 750,
                'observations' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.raison', 'Maux de tête persistants')
            ->assertJsonPath('data.cout_soins', 750);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->getJson('/api/v1/infirmerie/visites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->deleteJson("/api/v1/infirmerie/visites/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('visites_infirmerie', ['id' => $id]);
    }

    public function test_le_materiel_de_l_inventaire_consomme_decremente_le_stock_et_le_cout_total_se_recalcule(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'Elites Test',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $school->id,
            'nom' => '6e A',
        ]);

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Bruno Essomba',
            'sexe' => 'M',
            'statut' => 'actif',
            'groupe_sanguin' => 'O+',
            'allergies' => 'Pénicilline',
        ]);

        $malaise = MalaiseReferentiel::create([
            'school_id' => $school->id,
            'label_fr' => 'Fièvre',
            'label_en' => 'Fever',
        ]);

        $article = InventaireArticle::create([
            'school_id' => $school->id,
            'nom' => 'Paracétamol 500mg',
            // SQLite (tests) garde le CHECK de l'enum d'origine — 'medical' n'est
            // ajouté que côté MySQL (cf. migration add_medical_categorie_to_...).
            'categorie' => 'autre',
            'quantite' => 20,
            'etat' => 'bon',
            'valeur_unitaire' => 100,
        ]);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root2@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        // Règle de trois : 3 comprimés à 100 F l'unité -> 300 F de matériel, plus
        // 200 F "autre matériel" -> 500 F de coût total (le coût des soins reste à 0).
        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/infirmerie/visites', [
                'eleve_id' => $eleve->id,
                'date_visite' => '2026-08-20T09:00',
                'raison' => 'Fièvre à 39°C',
                'malaise_ids' => [$malaise->id],
                'soins_prodiges' => 'Prise de paracétamol, surveillance',
                'type_traitement' => 'externe',
                'structure_externe' => 'Hôpital de district de Bertoua',
                'materiels' => [
                    ['inventaire_article_id' => $article->id, 'quantite' => 3],
                ],
                'autre_materiel' => 'Compresse achetée en pharmacie',
                'cout_autre_materiel' => 200,
            ]);

        $creation->assertCreated()
            ->assertJsonPath('data.type_traitement', 'externe')
            ->assertJsonPath('data.structure_externe', 'Hôpital de district de Bertoua')
            ->assertJsonPath('data.malaises.0.label_fr', 'Fièvre')
            ->assertJsonPath('data.materiels.0.cout', 300)
            ->assertJsonPath('data.cout_materiels', 300)
            ->assertJsonPath('data.cout_total', 500);

        $this->assertDatabaseHas('inventaire_articles', ['id' => $article->id, 'quantite' => 17]);

        $id = $creation->json('data.id');

        // Ramène la quantité utilisée de 3 à 1 comprimé : le stock doit être
        // restitué puis re-décrémenté (20 - 1 = 19), pas s'accumuler.
        $modification = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/infirmerie/visites/{$id}", [
                'eleve_id' => $eleve->id,
                'date_visite' => '2026-08-20T09:00',
                'raison' => 'Fièvre à 39°C',
                'malaise_ids' => [$malaise->id],
                'soins_prodiges' => 'Prise de paracétamol, surveillance',
                'type_traitement' => 'interne',
                'materiels' => [
                    ['inventaire_article_id' => $article->id, 'quantite' => 1],
                ],
                'cout_autre_materiel' => 0,
            ]);

        $modification->assertOk()
            ->assertJsonPath('data.cout_materiels', 100)
            ->assertJsonPath('data.cout_total', 100);

        $this->assertDatabaseHas('inventaire_articles', ['id' => $article->id, 'quantite' => 19]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->deleteJson("/api/v1/infirmerie/visites/{$id}")
            ->assertOk();

        $this->assertDatabaseHas('inventaire_articles', ['id' => $article->id, 'quantite' => 20]);
    }
}
