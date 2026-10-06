<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmploiDuTempsSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private School $otherSchool;
    private Classe $classe;
    private Classe $associee;
    private Classe $otherClasse;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Primaire', 'code' => 'PRI', 'type' => 'primaire', 'is_active' => true]);
        $this->otherSchool = School::create(['name' => 'Secondaire', 'code' => 'SEC', 'type' => 'secondaire', 'is_active' => true]);
        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CP A']);
        $this->associee = Classe::create(['school_id' => $this->school->id, 'nom' => 'CP B']);
        $this->otherClasse = Classe::create(['school_id' => $this->otherSchool->id, 'nom' => '6 A']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'sanctum');
    }

    private function creneau(Classe $classe, string $type = 'cours'): EmploiDuTemps
    {
        $matiere = Matiere::firstOrCreate(['school_id' => $classe->school_id, 'nom' => 'Maths']);
        $affectation = ClasseMatiere::firstOrCreate(['classe_id' => $classe->id, 'matiere_id' => $matiere->id], ['coefficient' => 1]);

        return EmploiDuTemps::create([
            'school_id' => $classe->school_id,
            'classe_id' => $classe->id,
            'classe_matiere_id' => $type === 'cours' ? $affectation->id : null,
            'type' => $type,
            'libelle' => $type === 'cours' ? null : 'Recreation',
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
    }

    public function test_les_compteurs_incluent_le_tronc_commun_et_les_classes_vides_sans_les_pauses(): void
    {
        $cours = $this->creneau($this->classe);
        $cours->synchroniserClassesAssociees([$this->associee->id]);
        $this->creneau($this->classe, 'pause');

        $response = $this->getJson('/api/v1/emploi-du-temps/classes')->assertOk();
        $comptes = collect($response->json('data'))->keyBy('id');
        $this->assertSame(1, $comptes[$this->classe->id]['cours_planifies']);
        $this->assertSame(1, $comptes[$this->associee->id]['cours_planifies']);
        $this->assertSame(0, $comptes[$this->otherClasse->id]['cours_planifies']);
        $this->assertSame('Primaire', $comptes[$this->classe->id]['school']['name']);

        $this->withHeader('X-School-Id', (string) $this->otherSchool->id)
            ->getJson('/api/v1/emploi-du-temps/classes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_la_suppression_ecole_conserve_les_autres_ecoles_et_les_seances(): void
    {
        $cours = $this->creneau($this->classe);
        $cours->synchroniserClassesAssociees([$this->associee->id]);
        $pause = $this->creneau($this->associee, 'pause');
        $autre = $this->creneau($this->otherClasse);
        $seance = Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $cours->classe_matiere_id, 'emploi_du_temps_id' => $cours->id,
            'date_seance' => '2026-10-05', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'effectuee',
        ]);

        $this->deleteJson('/api/v1/emploi-du-temps', ['school_id' => $this->school->id])
            ->assertOk()->assertJsonPath('data.deleted', 2);

        $this->assertModelMissing($cours);
        $this->assertModelMissing($pause);
        $this->assertModelExists($autre);
        $this->assertSame('effectuee', $seance->refresh()->statut);
        $this->assertNull($seance->emploi_du_temps_id);
        $this->assertDatabaseMissing('emploi_du_temps_classe', ['emploi_du_temps_id' => $cours->id]);
        foreach ([$cours, $pause] as $creneau) {
            $this->assertDatabaseHas('sync_tombstones', ['entite' => 'emplois_du_temps', 'entite_id' => $creneau->id]);
        }
    }

    public function test_la_suppression_selection_detache_les_cours_portes_par_une_autre_classe(): void
    {
        $cours = $this->creneau($this->classe);
        $cours->synchroniserClassesAssociees([$this->associee->id]);
        $propre = $this->creneau($this->associee);

        $this->deleteJson('/api/v1/emploi-du-temps', ['classe_ids' => [$this->associee->id]])
            ->assertOk()->assertJsonPath('data.deleted', 1);

        $this->assertModelExists($cours);
        $this->assertModelMissing($propre);
        $this->assertSame(0, $cours->classesAssociees()->count());
        $this->getJson('/api/v1/emploi-du-temps/classes')->assertOk()
            ->assertJsonFragment(['id' => $this->associee->id, 'cours_planifies' => 0]);
    }

    public function test_une_cible_est_obligatoire_et_les_deux_modes_ne_se_melangent_pas(): void
    {
        $cours = $this->creneau($this->classe);
        foreach ([[], ['classe_ids' => []], ['school_id' => $this->school->id, 'classe_ids' => [$this->classe->id]]] as $payload) {
            $this->deleteJson('/api/v1/emploi-du-temps', $payload)->assertUnprocessable();
        }
        $this->assertModelExists($cours);
    }

    public function test_le_focus_ecole_refuse_une_cible_hors_perimetre(): void
    {
        $cours = $this->creneau($this->otherClasse);
        $this->withHeader('X-School-Id', (string) $this->school->id)
            ->deleteJson('/api/v1/emploi-du-temps', ['school_id' => $this->otherSchool->id])->assertForbidden();
        $this->deleteJson('/api/v1/emploi-du-temps', ['classe_ids' => [$this->classe->id, $this->otherClasse->id]])
            ->assertForbidden();
        $this->assertModelExists($cours);
    }

    public function test_la_suppression_exige_le_privilege_et_le_bornage_est_respecte(): void
    {
        $cours = $this->creneau($this->classe);
        $user = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->assertFalse($user->aLaPermission('emploi_du_temps.delete'));
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/emploi-du-temps', ['school_id' => $this->school->id])
            ->assertForbidden();

        Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $user->assignRole('enseignant');
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'emploi_du_temps.delete', 'guard_name' => 'web']));
        $fonction = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Enseignant', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->actingAs($user->fresh(), 'sanctum')->deleteJson('/api/v1/emploi-du-temps', ['school_id' => $this->school->id])
            ->assertForbidden();
        $this->deleteJson('/api/v1/emploi-du-temps', ['classe_ids' => [$this->classe->id]])->assertForbidden();
        $this->assertModelExists($cours);
    }
}
