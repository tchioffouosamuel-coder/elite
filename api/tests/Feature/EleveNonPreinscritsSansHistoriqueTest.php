<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Observation;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Garde-fou pour une fonctionnalité destructrice et irréversible (Eleve n'a
 * pas de suppression douce) : vérifie que seules les fiches réellement sans
 * la moindre trace d'activité sont candidates, et qu'un ancien élève avec un
 * historique (préinscription, note…) n'est jamais supprimé même sans classe.
 */
class EleveNonPreinscritsSansHistoriqueTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleveVide(string $nom): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => null, 'nom_complet' => $nom,
            'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    public function test_une_fiche_doublon_sans_classe_ni_historique_est_candidate_et_supprimee(): void
    {
        $doublon = $this->eleveVide('Doublon Vide');

        $apercu = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/eleves/non-preinscrits-sans-historique');

        $apercu->assertOk();
        $this->assertContains($doublon->id, collect($apercu->json('data'))->pluck('id')->all());

        $suppression = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/eleves/non-preinscrits-sans-historique');

        $suppression->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertDatabaseMissing('eleves', ['id' => $doublon->id]);
    }

    public function test_un_eleve_avec_une_classe_nest_jamais_candidat(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 A']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'nom_complet' => 'Avec Classe',
            'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/eleves/non-preinscrits-sans-historique')
            ->assertOk()->assertJsonPath('data.deleted', 0);

        $this->assertDatabaseHas('eleves', ['id' => $eleve->id]);
    }

    /** Un ancien élève parti, sans classe cette année, mais dont on garde la trace d'inscription : jamais supprimé. */
    public function test_un_ancien_eleve_non_reinscrit_avec_preinscription_nest_pas_supprime(): void
    {
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => now()->subYear(), 'date_fin' => now()->subMonths(2), 'is_active' => false,
        ]);
        $ancien = $this->eleveVide('Ancien Parti');
        Preinscription::create([
            'school_id' => $this->school->id, 'eleve_id' => $ancien->id, 'annee_scolaire_id' => $annee->id,
            'type' => 'existant', 'statut' => 'validee', 'donnees_eleve' => [], 'donnees_tuteurs' => [],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/eleves/non-preinscrits-sans-historique')
            ->assertOk()->assertJsonPath('data.deleted', 0);

        $this->assertDatabaseHas('eleves', ['id' => $ancien->id]);
    }

    /** Une observation existe (donc l'élève a réellement un dossier suivi) : jamais supprimé, même sans classe ni préinscription aujourd'hui. */
    public function test_un_eleve_avec_une_observation_nest_pas_supprime(): void
    {
        $eleve = $this->eleveVide('Avec Observation');
        Observation::create([
            'school_id' => $this->school->id, 'eleve_id' => $eleve->id, 'user_id' => $this->admin->id,
            'contenu' => 'Suivi particulier.',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/eleves/non-preinscrits-sans-historique')
            ->assertOk()->assertJsonPath('data.deleted', 0);

        $this->assertDatabaseHas('eleves', ['id' => $eleve->id]);
    }
}
