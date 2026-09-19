<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un profil « consultation + dépenses » (typiquement une assistante) ne doit
 * plus pouvoir souscrire ou retirer un élève du transport — seul un rôle
 * disposant explicitement de `bus.souscrire` le peut. La consultation reste
 * ouverte aux deux.
 */
class BusPermissionSouscrireTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private BusTrajet $trajet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->trajet = BusTrajet::create([
            'school_id' => $this->school->id,
            'nom' => 'Ligne Nord',
            'tarif_aller_retour' => 15000,
        ]);
    }

    private function eleve(string $matricule): Eleve
    {
        $classe = Classe::firstOrCreate(['school_id' => $this->school->id, 'nom' => 'CM2']);

        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => $matricule,
            'nom_complet' => 'Élève ' . $matricule,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function utilisateur(array $permissions): User
    {
        Permission::firstOrCreate(['name' => 'bus.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'bus.souscrire', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'finance.depenses', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'assistant_test', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::create([
            'name' => 'Assistante', 'email' => 'assistante@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_consultation_et_depenses_seul_ne_peut_pas_souscrire_un_eleve(): void
    {
        $assistante = $this->utilisateur(['bus.view', 'finance.depenses']);
        $eleve = $this->eleve('A1');

        $this->actingAs($assistante, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/bus/affectations', [
                'eleve_id' => $eleve->id,
                'trajet_id' => $this->trajet->id,
                'option_trajet' => 'aller_retour',
            ])
            ->assertForbidden();
    }

    public function test_consultation_et_depenses_seul_ne_peut_pas_retirer_une_affectation(): void
    {
        $assistante = $this->utilisateur(['bus.view', 'finance.depenses']);
        $eleve = $this->eleve('A2');
        $affectation = BusAffectation::create([
            'eleve_id' => $eleve->id,
            'trajet_id' => $this->trajet->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 15000,
            'option_trajet' => 'aller_retour',
            'statut' => 'actif',
        ]);

        $this->actingAs($assistante, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson("/api/v1/bus/affectations/{$affectation->id}")
            ->assertForbidden();
    }

    public function test_consultation_et_depenses_seul_peut_toujours_consulter(): void
    {
        $assistante = $this->utilisateur(['bus.view', 'finance.depenses']);
        $this->eleve('A3');

        $this->actingAs($assistante, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/bus/affectations')
            ->assertOk();
    }

    public function test_avec_bus_souscrire_peut_souscrire_un_eleve(): void
    {
        $gestionnaire = $this->utilisateur(['bus.view', 'bus.souscrire']);
        $eleve = $this->eleve('A4');

        $this->actingAs($gestionnaire, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/bus/affectations', [
                'eleve_id' => $eleve->id,
                'trajet_id' => $this->trajet->id,
                'option_trajet' => 'aller_retour',
            ])
            ->assertCreated();
    }
}
