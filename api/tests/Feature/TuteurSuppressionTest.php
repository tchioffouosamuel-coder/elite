<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suppression d'une fiche tuteur — distincte de la suppression de son seul
 * accès au portail (cf. CompteParentLotTest pour l'ouverture des accès).
 */
class TuteurSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->gestionnaire = User::create([
            'name' => 'Gestionnaire', 'email' => 'gestionnaire@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'gestion', 'guard_name' => 'web']);
        $role->syncPermissions(['eleves.manage']);
        $this->gestionnaire->assignRole($role);
    }

    public function test_supprimer_le_tuteur_retire_sa_fiche_et_son_lien_avec_ses_enfants(): void
    {
        $tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'ACHU EDMUND', 'telephone' => '675822844']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'nom_complet' => 'ACHU JUNIOR', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($tuteur->id, ['lien_parente' => 'Père', 'is_principal' => true]);

        $this->actingAs($this->gestionnaire, 'sanctum')
            ->deleteJson("/api/v1/tuteurs/{$tuteur->id}")
            ->assertOk();

        $this->assertDatabaseMissing('tuteurs', ['id' => $tuteur->id]);
        $this->assertDatabaseMissing('eleve_tuteur', ['tuteur_id' => $tuteur->id]);
        // La fiche de l'enfant n'est jamais touchée par la suppression d'un tuteur.
        $this->assertDatabaseHas('eleves', ['id' => $eleve->id]);
    }

    public function test_supprimer_le_tuteur_supprime_aussi_son_compte_parent(): void
    {
        $compte = User::create([
            'name' => 'ACHU EDMUND', 'phone' => '675822844', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $compte->assignRole('parent');
        $tuteur = Tuteur::create([
            'school_id' => $this->school->id, 'user_id' => $compte->id, 'nom_complet' => 'ACHU EDMUND', 'telephone' => '675822844',
        ]);

        $this->actingAs($this->gestionnaire, 'sanctum')
            ->deleteJson("/api/v1/tuteurs/{$tuteur->id}")
            ->assertOk();

        $this->assertDatabaseMissing('tuteurs', ['id' => $tuteur->id]);
        $this->assertDatabaseMissing('users', ['id' => $compte->id]);
    }

    public function test_la_suppression_est_refusee_sans_le_privilege(): void
    {
        $sansPrivilege = User::create([
            'name' => 'Sans Privilège', 'email' => 'sp@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'ACHU EDMUND']);

        $this->actingAs($sansPrivilege, 'sanctum')
            ->deleteJson("/api/v1/tuteurs/{$tuteur->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('tuteurs', ['id' => $tuteur->id]);
    }

    public function test_un_tuteur_d_une_autre_ecole_est_introuvable(): void
    {
        $autreEcole = School::create(['name' => 'Autre École', 'code' => 'AE', 'type' => 'secondaire', 'is_active' => true]);
        $tuteur = Tuteur::create(['school_id' => $autreEcole->id, 'nom_complet' => 'HORS PERIMETRE']);

        $this->actingAs($this->gestionnaire, 'sanctum')
            ->deleteJson("/api/v1/tuteurs/{$tuteur->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('tuteurs', ['id' => $tuteur->id]);
    }
}
