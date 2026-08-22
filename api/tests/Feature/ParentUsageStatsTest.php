<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Eleve;
use App\Models\JustificationAbsence;
use App\Models\ModificationEleve;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentUsageStatsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function parentUser(string $email): User
    {
        $user = User::create([
            'name' => 'Parent', 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('parent');

        return $user;
    }

    public function test_le_resume_reflete_adoption_activite_volumes_et_delai(): void
    {
        $admin = $this->admin();

        // Adoption : 2 tuteurs sur 3 ont un compte parent.
        $parent1 = $this->parentUser('p1@test.local');
        $parent2 = $this->parentUser('p2@test.local');
        Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Tuteur A', 'user_id' => $parent1->id]);
        Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Tuteur B', 'user_id' => $parent2->id]);
        Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Tuteur C']);

        // Activité : 3 connexions parent, dont 2 comptes distincts, + 1 connexion
        // non-parent qui ne doit pas compter.
        ActivityLog::enregistrer($parent1, 'connexion', 'Connexion.');
        ActivityLog::enregistrer($parent1, 'connexion', 'Connexion.');
        ActivityLog::enregistrer($parent2, 'connexion', 'Connexion.');
        ActivityLog::enregistrer($admin, 'connexion', 'Connexion.');

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC1', 'nom_complet' => 'Fomesso Mark',
            'sexe' => 'M', 'statut' => 'actif',
        ]);
        $tuteurEleve = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Tuteur D']);

        // Volumes + délai de traitement : une préinscription déposée puis
        // traitée 4 h plus tard. `created_at`/`updated_at` ne sont pas
        // mass-assignables (hors `$fillable`) : `forceFill` les impose après
        // coup, sans quoi Eloquent y substituerait l'horodatage réel.
        Preinscription::create([
            'school_id' => $this->school->id, 'tuteur_id' => $tuteurEleve->id, 'type' => 'nouveau',
            'statut' => 'validee', 'donnees_eleve' => ['nom_complet' => 'X'], 'donnees_tuteurs' => [],
            'traite_le' => now()->subDays(2)->addHours(4),
        ])->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        ModificationEleve::create([
            'school_id' => $this->school->id, 'eleve_id' => $eleve->id, 'tuteur_id' => $tuteurEleve->id,
            'donnees' => ['adresse' => 'Y'], 'statut' => 'en_attente',
        ]);

        JustificationAbsence::create([
            'school_id' => $this->school->id, 'eleve_id' => $eleve->id, 'tuteur_id' => $tuteurEleve->id,
            'date_debut' => now()->toDateString(), 'date_fin' => now()->toDateString(),
            'motif' => 'maladie', 'statut' => 'en_attente',
        ]);

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/parent-usage-stats?jours=30');

        $reponse->assertOk()
            ->assertJsonPath('data.adoption.tuteurs_total', 4)
            ->assertJsonPath('data.adoption.comptes_parent_total', 2)
            ->assertJsonPath('data.adoption.taux_adoption', 50)
            ->assertJsonPath('data.activite.connexions_totales', 3)
            ->assertJsonPath('data.activite.parents_actifs_distincts', 2)
            ->assertJsonPath('data.volumes.preinscriptions.total', 1)
            ->assertJsonPath('data.volumes.preinscriptions.repartition.validee', 1)
            ->assertJsonPath('data.volumes.modifications.total', 1)
            ->assertJsonPath('data.volumes.modifications.repartition.en_attente', 1)
            ->assertJsonPath('data.volumes.justifications.total', 1)
            ->assertJsonPath('data.efficience.delai_moyen_preinscriptions_heures', 4);
    }
}
