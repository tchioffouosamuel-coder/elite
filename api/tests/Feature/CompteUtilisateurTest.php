<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FonctionReferentiel;
use App\Models\School;
use App\Models\User;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompteUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $root;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->root->assignRole('super_admin');

        $fonction = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $fonction->synchroniserPermissions(['eleves.view']);

        $personnel = app(PersonnelService::class)->create($this->school->id, [
            'nom_complet' => 'AGBORNDE CATHERINE',
            'fonction_id' => $fonction->id,
            'statut' => 'actif',
        ]);
        $this->agent = $personnel->user;
        // Hors sujet ici (couvert par MotDePasseProvisoireTest) : sans ça, le
        // middleware de renouvellement obligatoire intercepterait chaque
        // requête avant même d'atteindre la vérification super admin.
        $this->agent->forceFill(['doit_changer_mot_de_passe' => false])->save();
    }

    public function test_la_liste_des_comptes_est_reservee_au_super_admin(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/v1/comptes-utilisateurs')
            ->assertStatus(403);
    }

    public function test_le_super_admin_voit_tous_les_comptes_avec_leur_type(): void
    {
        $reponse = $this->actingAs($this->root, 'sanctum')
            ->getJson('/api/v1/comptes-utilisateurs')
            ->assertOk();

        $comptes = collect($reponse->json('data'));

        $this->assertTrue($comptes->contains(fn ($c) => $c['id'] === $this->agent->id && $c['type'] === 'personnel'));
        $this->assertTrue($comptes->contains(fn ($c) => $c['id'] === $this->root->id && $c['type'] === 'super_admin'));
    }

    /**
     * Le compte racine créé par DatabaseSeeder n'a pas de `school_id` (rattaché
     * à aucun établissement en particulier) : sans traitement à part, il
     * sortirait du périmètre `Tenant::schoolIds()` et s'exclurait lui-même de
     * sa propre liste.
     */
    public function test_un_super_admin_sans_ecole_reste_visible_dans_sa_propre_liste(): void
    {
        $racine = User::create(['name' => 'Racine', 'email' => 'racine@test.local', 'password' => 'password', 'is_active' => true]);
        $racine->assignRole('super_admin');

        $reponse = $this->actingAs($this->root, 'sanctum')
            ->getJson('/api/v1/comptes-utilisateurs')
            ->assertOk();

        $comptes = collect($reponse->json('data'));
        $this->assertTrue($comptes->contains(fn ($c) => $c['id'] === $racine->id));

        $this->actingAs($this->root, 'sanctum')
            ->getJson("/api/v1/comptes-utilisateurs/{$racine->id}/activite")
            ->assertOk();
    }

    public function test_le_super_admin_reinitialise_le_mot_de_passe_d_un_compte_sans_l_ancien(): void
    {
        $this->agent->createToken('mobile');
        $this->assertSame(1, $this->agent->tokens()->count());

        $this->actingAs($this->root, 'sanctum')
            ->postJson("/api/v1/comptes-utilisateurs/{$this->agent->id}/reinitialiser-mot-de-passe", [
                'nouveau_mot_de_passe' => 'Bertoua2026',
                'nouveau_mot_de_passe_confirmation' => 'Bertoua2026',
            ])
            ->assertOk();

        $agent = $this->agent->fresh();
        $this->assertTrue(Hash::check('Bertoua2026', $agent->password));
        $this->assertTrue($agent->doit_changer_mot_de_passe);
        // La réinitialisation ferme les sessions déjà ouvertes sur ce compte.
        $this->assertSame(0, $agent->tokens()->count());

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'reinitialisation_mot_de_passe',
            'user_id' => $this->root->id,
            'subject_type' => User::class,
            'subject_id' => $this->agent->id,
        ]);
    }

    public function test_la_reinitialisation_exige_une_confirmation_correspondante(): void
    {
        $this->actingAs($this->root, 'sanctum')
            ->postJson("/api/v1/comptes-utilisateurs/{$this->agent->id}/reinitialiser-mot-de-passe", [
                'nouveau_mot_de_passe' => 'Bertoua2026',
                'nouveau_mot_de_passe_confirmation' => 'Autrechose',
            ])
            ->assertStatus(422);
    }

    public function test_un_agent_ne_peut_pas_reinitialiser_un_mot_de_passe(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->postJson("/api/v1/comptes-utilisateurs/{$this->root->id}/reinitialiser-mot-de-passe", [
                'nouveau_mot_de_passe' => 'Bertoua2026',
                'nouveau_mot_de_passe_confirmation' => 'Bertoua2026',
            ])
            ->assertStatus(403);
    }

    public function test_le_journal_d_activite_d_un_compte_liste_les_actions_dont_il_est_la_cible(): void
    {
        ActivityLog::enregistrer($this->root, 'reinitialisation_mot_de_passe', 'Mot de passe réinitialisé pour AGBORNDE CATHERINE.', $this->agent);

        $this->actingAs($this->root, 'sanctum')
            ->getJson("/api/v1/comptes-utilisateurs/{$this->agent->id}/activite")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'reinitialisation_mot_de_passe');
    }
}
