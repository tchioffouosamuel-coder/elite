<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * `GET /valeurs-suggerees` : autocomplétion des champs texte libres du
 * formulaire d'inscription (lieu de naissance, adresse...) à partir des
 * valeurs déjà enregistrées pour l'école courante — recherche insensible à
 * la casse et aux accents, et toujours scopée à l'école de la requête.
 */
class ValeurSuggereeTest extends TestCase
{
    use RefreshDatabase;

    private School $ecoleA;

    private School $ecoleB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'eleves.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.update', 'guard_name' => 'web']);

        $this->ecoleA = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);
        $this->ecoleB = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->ecoleA->id, 'is_active' => true,
        ]);
        $this->admin->givePermissionTo('eleves.create');
    }

    private function eleve(School $ecole, string $matricule, ?string $lieuNaissance): Eleve
    {
        return Eleve::create([
            'school_id' => $ecole->id,
            'matricule' => $matricule,
            'nom_complet' => 'UN ELEVE',
            'sexe' => 'M',
            'lieu_naissance' => $lieuNaissance,
            'statut' => 'actif',
        ]);
    }

    public function test_recherche_insensible_a_la_casse_et_aux_accents(): void
    {
        $this->eleve($this->ecoleA, '25ELITES-0001', 'Yaoundé');
        $this->eleve($this->ecoleA, '25ELITES-0002', 'Douala');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.lieu_naissance&q=yaounde')
            ->assertOk()
            ->json('data');

        $this->assertSame(['valeurs' => ['Yaoundé']], $reponse);
    }

    public function test_scope_a_l_ecole_courante(): void
    {
        $this->eleve($this->ecoleA, '25ELITES-0003', 'Yaoundé');
        $this->eleve($this->ecoleB, '25ELITES-0004', 'Yaoundé 2');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.lieu_naissance&q=yaound')
            ->assertOk()
            ->json('data.valeurs');

        $this->assertSame(['Yaoundé'], $reponse);
    }

    public function test_valeurs_nulles_ou_vides_sont_ignorees(): void
    {
        $this->eleve($this->ecoleA, '25ELITES-0005', 'Yaoundé');
        $this->eleve($this->ecoleA, '25ELITES-0006', null);
        $this->eleve($this->ecoleA, '25ELITES-0007', '');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.lieu_naissance')
            ->assertOk()
            ->json('data.valeurs');

        $this->assertSame(['Yaoundé'], $reponse);
    }

    public function test_champ_hors_liste_blanche_est_refuse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.matricule&q=25')
            ->assertStatus(422);
    }

    public function test_sans_permission_eleves_create_ou_update_est_refuse(): void
    {
        $utilisateurSansDroit = User::create([
            'name' => 'Sans Droit', 'email' => 'sansdroit@test.local', 'password' => 'password',
            'school_id' => $this->ecoleA->id, 'is_active' => true,
        ]);

        $this->actingAs($utilisateurSansDroit, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.lieu_naissance&q=yaounde')
            ->assertStatus(403);
    }

    public function test_sans_authentification_est_refuse(): void
    {
        $this->withHeader('X-School-Id', $this->ecoleA->id)
            ->getJson('/api/v1/valeurs-suggerees?champ=eleves.lieu_naissance&q=yaounde')
            ->assertStatus(401);
    }
}
