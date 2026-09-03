<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\NotificationInterne;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\JustificationAbsenceService;
use App\Services\ObservationService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Consultation admin des justifications d'absence et des observations
 * déposées par les parents, et la notification qu'elles déclenchent vers le
 * personnel — cf. JustificationAbsenceService::soumettre() et
 * ObservationService::creer().
 */
class DemarchesParentAdminTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Eleve $eleve;

    private Tuteur $tuteur;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->eleve = Eleve::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Eleve Test', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Tuteur Test', 'telephone' => '677000000']);
        $this->tuteur->eleves()->attach($this->eleve->id, ['lien_parente' => 'Père']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->givePermissionTo('eleves.manage');
    }

    private function parentUser(): User
    {
        $user = User::create([
            'name' => $this->tuteur->nom_complet, 'phone' => '699000000',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('parent');
        $this->tuteur->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    // --------------------------------------------------------- Justifications

    public function test_soumettre_une_justification_notifie_le_personnel_habilite(): void
    {
        $autreAdmin = User::create([
            'name' => 'Second Admin', 'email' => 'second@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $autreAdmin->givePermissionTo('eleves.manage');

        $sansPermission = User::create([
            'name' => 'Sans Permission', 'email' => 'sanspermission@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $justification = app(JustificationAbsenceService::class)->soumettre($this->tuteur, $this->eleve, [
            'date_debut' => now()->toDateString(),
            'motif' => 'maladie',
            'description' => 'Grippe.',
        ]);

        $this->assertSame(2, NotificationInterne::where('type', 'justification_absence')->count());
        $this->assertTrue(NotificationInterne::where('user_id', $this->admin->id)->where('lien', "/justifications?id={$justification->id}")->exists());
        $this->assertTrue(NotificationInterne::where('user_id', $autreAdmin->id)->exists());
        $this->assertFalse(NotificationInterne::where('user_id', $sansPermission->id)->where('type', 'justification_absence')->exists());
    }

    public function test_route_liste_les_justifications_avec_filtre_statut(): void
    {
        app(JustificationAbsenceService::class)->soumettre($this->tuteur, $this->eleve, ['date_debut' => now()->toDateString(), 'motif' => 'maladie']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/justifications?statut=en_attente')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.eleve.nom_complet', 'Eleve Test');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/justifications?statut=appliquee')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_route_detail_dune_justification(): void
    {
        $justification = app(JustificationAbsenceService::class)->soumettre($this->tuteur, $this->eleve, [
            'date_debut' => now()->toDateString(), 'motif' => 'permission', 'description' => 'Voyage familial.',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/justifications/{$justification->id}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Voyage familial.')
            ->assertJsonPath('data.motif', 'permission');
    }

    // ----------------------------------------------------------- Observations

    public function test_une_observation_dun_parent_notifie_le_personnel_mais_pas_une_reponse_admin(): void
    {
        $parent = $this->parentUser();

        $observation = app(ObservationService::class)->creer($this->eleve, $parent, 'Mon enfant a été malade hier.');

        $this->assertSame(1, NotificationInterne::where('type', 'observation')->count());
        $this->assertTrue(NotificationInterne::where('user_id', $this->admin->id)->where('lien', "/observations?id={$observation->id}")->exists());

        app(ObservationService::class)->creer($this->eleve, $this->admin, 'Merci, bon rétablissement.');

        $this->assertSame(1, NotificationInterne::where('type', 'observation')->count());
    }

    public function test_route_liste_les_fils_dobservations_regroupes_par_eleve(): void
    {
        $parent = $this->parentUser();
        app(ObservationService::class)->creer($this->eleve, $parent, 'Premier message.');
        app(ObservationService::class)->creer($this->eleve, $this->admin, 'Réponse.');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/observations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total', 2)
            ->assertJsonPath('data.0.derniere_origine', 'ecole');
    }

    public function test_route_detail_et_reponse_dun_fil_dobservations(): void
    {
        $parent = $this->parentUser();
        app(ObservationService::class)->creer($this->eleve, $parent, 'Premier message.');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/observations/{$this->eleve->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.observations')
            ->assertJsonPath('data.observations.0.origine', 'parent');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/observations/{$this->eleve->id}", ['contenu' => 'Bien reçu.'])
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/observations/{$this->eleve->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.observations');
    }

    // ---------------------------------------------------------------- Accès

    public function test_un_compte_sans_permission_est_refuse(): void
    {
        $sansPermission = User::create([
            'name' => 'Sans Permission', 'email' => 'nopermission@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($sansPermission, 'sanctum')->getJson('/api/v1/justifications')->assertForbidden();
        $this->actingAs($sansPermission, 'sanctum')->getJson('/api/v1/observations')->assertForbidden();
    }
}
