<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\RegleValidationSeance;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegleValidationSeanceAppliquerTest extends TestCase
{
    use RefreshDatabase;

    public function test_appliquer_remet_tous_les_enseignants_de_l_ecole_en_heritage(): void
    {
        Permission::firstOrCreate(['name' => 'regles_seance.update', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);
        $autreSchool = School::create([
            'name' => 'Autre Ecole', 'code' => 'AE', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');
        $admin->givePermissionTo('regles_seance.update');

        $fonctionEnseignant = FonctionReferentiel::create([
            'school_id' => $school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonctionSupport = FonctionReferentiel::create([
            'school_id' => $school->id, 'label_fr' => 'Chauffeur',
        ]);
        $fonctionAutreEcole = FonctionReferentiel::create([
            'school_id' => $autreSchool->id, 'label_fr' => 'Enseignant',
        ]);

        $profCode = $this->agent($school, $fonctionEnseignant, 'Prof Code', 'code');
        $profLibre = $this->agent($school, $fonctionEnseignant, 'Prof Libre', 'libre');
        $profDejaHeritier = $this->agent($school, $fonctionEnseignant, 'Prof Heritier', null);
        $chauffeur = $this->agent($school, $fonctionSupport, 'Chauffeur', 'code');
        $profAutreEcole = $this->agent($autreSchool, $fonctionAutreEcole, 'Prof Autre', 'code');

        $sousSysteme = SousSysteme::create([
            'school_id' => $school->id, 'code' => 'FR', 'nom' => 'Francophone',
        ]);
        $regle = RegleValidationSeance::create([
            'school_id' => $school->id,
            'sous_systeme_id' => $sousSysteme->id,
            'methode_validation' => 'qr',
            'delai_valeur' => 15,
            'delai_unite' => 'minutes',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson("/api/v1/regles-validation-seance/{$regle->id}/appliquer")
            ->assertOk()
            ->assertJsonPath('data.agents', 2);

        $this->assertNull($profCode->fresh()->methode_validation_seance);
        $this->assertNull($profLibre->fresh()->methode_validation_seance);
        $this->assertNull($profDejaHeritier->fresh()->methode_validation_seance);
        $this->assertSame('code', $chauffeur->fresh()->methode_validation_seance);
        $this->assertSame('code', $profAutreEcole->fresh()->methode_validation_seance);
    }

    private function agent(School $school, FonctionReferentiel $fonction, string $nom, ?string $methode): Personnel
    {
        return Personnel::create([
            'school_id' => $school->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
            'methode_validation_seance' => $methode,
        ]);
    }
}
