<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
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
            'annee_scolaire_id' => $annee->id,
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
                'cout_soins' => 500,
                'observations' => 'Parent informé.',
            ]);

        $creation->assertCreated()
            ->assertJsonPath('data.eleve.nom_complet', 'Alice Ngono')
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.cout_soins', 500);

        $id = $creation->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/infirmerie/visites/{$id}", [
                'eleve_id' => $eleve->id,
                'date_visite' => '2026-08-17T11:00',
                'raison' => 'Maux de tête persistants',
                'soins_prodiges' => 'Repos prolongé',
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
}
