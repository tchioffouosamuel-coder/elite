<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusTrajet;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusAffectationBatchDestroyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private BusTrajet $trajet;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

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

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleve(string $matricule): Eleve
    {
        $classe = Classe::firstOrCreate(['school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1-A']);

        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => $matricule,
            'nom_complet' => 'Élève ' . $matricule,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function affecter(Eleve $eleve): BusAffectation
    {
        return BusAffectation::create([
            'eleve_id' => $eleve->id,
            'trajet_id' => $this->trajet->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 15000,
            'option_trajet' => 'aller_retour',
            'statut' => 'actif',
        ]);
    }

    public function test_supprime_les_affectations_sans_versement(): void
    {
        $a = $this->affecter($this->eleve('A1'));
        $b = $this->affecter($this->eleve('A2'));
        $autre = $this->affecter($this->eleve('A3'));

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/bus/affectations/batch-destroy', ['ids' => [$a->id, $b->id]]);

        $reponse->assertOk()->assertJsonPath('data.retirees', 2);

        $this->assertDatabaseMissing('bus_affectations', ['id' => $a->id]);
        $this->assertDatabaseMissing('bus_affectations', ['id' => $b->id]);
        $this->assertDatabaseHas('bus_affectations', ['id' => $autre->id]);
    }

    /** Une affectation avec des versements est suspendue plutôt que supprimée, pour garder son historique de paiement. */
    public function test_suspend_plutot_que_supprimer_une_affectation_avec_versements(): void
    {
        $affectation = $this->affecter($this->eleve('A4'));
        BusVersement::create([
            'school_id' => $this->school->id,
            'bus_affectation_id' => $affectation->id,
            'mois' => '2026-09-01',
            'numero_recu' => 'BUS-TEST-1',
            'date_versement' => '2026-09-05',
            'montant' => 15000,
            'mode' => 'especes',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/bus/affectations/batch-destroy', ['ids' => [$affectation->id]])
            ->assertOk()->assertJsonPath('data.retirees', 1);

        $this->assertDatabaseHas('bus_affectations', ['id' => $affectation->id, 'statut' => 'suspendu']);
    }

    public function test_ids_vides_est_refuse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->deleteJson('/api/v1/bus/affectations/batch-destroy', ['ids' => []])
            ->assertStatus(422);
    }
}
