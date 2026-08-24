<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\School;
use App\Models\User;
use App\Services\PersonnelService;
use App\Services\SettingsCatalog;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MotDePasseProvisoireTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $fonction = FonctionReferentiel::create(['school_id' => $school->id, 'label_fr' => 'Enseignant']);
        $fonction->synchroniserPermissions(['eleves.view']);

        $personnel = app(PersonnelService::class)->create($school->id, [
            'nom_complet' => 'AGBORNDE CATHERINE',
            'fonction_id' => $fonction->id,
            'statut' => 'actif',
        ]);

        $this->agent = $personnel->user;
    }

    public function test_un_compte_ouvert_automatiquement_doit_renouveler_son_mot_de_passe(): void
    {
        $this->assertTrue($this->agent->doit_changer_mot_de_passe);
    }

    public function test_la_connexion_signale_le_renouvellement_a_faire(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->agent->email,
            'password' => SettingsCatalog::default('mot_de_passe_defaut'),
        ])
            ->assertOk()
            ->assertJsonPath('data.user.doit_changer_mot_de_passe', true);
    }

    /** Le cœur de la garantie : le jeton ne sert à rien d'autre entre-temps. */
    public function test_les_routes_metier_sont_fermees_tant_que_le_mot_de_passe_est_provisoire(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/v1/eleves')
            ->assertStatus(423)
            ->assertJsonPath('errors.mot_de_passe_a_renouveler', true);
    }

    public function test_le_profil_et_la_deconnexion_restent_accessibles(): void
    {
        $this->actingAs($this->agent, 'sanctum')->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_le_renouvellement_exige_le_mot_de_passe_actuel(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/v1/auth/mot-de-passe', [
                'ancien_mot_de_passe' => 'mauvais',
                'nouveau_mot_de_passe' => 'Nouveau2026',
                'nouveau_mot_de_passe_confirmation' => 'Nouveau2026',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.ancien_mot_de_passe.0', 'Le mot de passe actuel est incorrect.');

        $this->assertTrue($this->agent->fresh()->doit_changer_mot_de_passe);
    }

    public function test_le_nouveau_mot_de_passe_doit_differer_de_l_ancien(): void
    {
        $defaut = SettingsCatalog::default('mot_de_passe_defaut');

        $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/v1/auth/mot-de-passe', [
                'ancien_mot_de_passe' => $defaut,
                'nouveau_mot_de_passe' => $defaut,
                'nouveau_mot_de_passe_confirmation' => $defaut,
            ])
            ->assertStatus(422);
    }

    public function test_apres_renouvellement_l_application_est_ouverte(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/v1/auth/mot-de-passe', [
                'ancien_mot_de_passe' => SettingsCatalog::default('mot_de_passe_defaut'),
                'nouveau_mot_de_passe' => 'Bertoua2026',
                'nouveau_mot_de_passe_confirmation' => 'Bertoua2026',
            ])
            ->assertOk()
            ->assertJsonPath('data.doit_changer_mot_de_passe', false);

        $agent = $this->agent->fresh();

        $this->assertFalse($agent->doit_changer_mot_de_passe);
        $this->assertTrue(Hash::check('Bertoua2026', $agent->password));

        $this->actingAs($agent, 'sanctum')->getJson('/api/v1/eleves')->assertOk();
    }

    /** Le mot de passe commun ayant pu servir ailleurs, les autres sessions tombent. */
    public function test_le_renouvellement_revoque_les_autres_jetons(): void
    {
        $this->agent->createToken('autre-appareil');
        $this->assertSame(1, $this->agent->tokens()->count());

        $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/v1/auth/mot-de-passe', [
                'ancien_mot_de_passe' => SettingsCatalog::default('mot_de_passe_defaut'),
                'nouveau_mot_de_passe' => 'Bertoua2026',
                'nouveau_mot_de_passe_confirmation' => 'Bertoua2026',
            ])
            ->assertOk();

        $this->assertSame(0, $this->agent->fresh()->tokens()->count());
    }

    public function test_un_compte_hors_personnel_n_est_pas_concerne(): void
    {
        $root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password', 'is_active' => true,
        ]);
        $root->assignRole('super_admin');

        $this->assertFalse((bool) $root->doit_changer_mot_de_passe);
    }
}
