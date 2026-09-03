<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use App\Services\ConseilClasseService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Clôture d'une année scolaire : archivage (exige un conseil validé pour
 * chaque classe non vide) puis bascule vers l'année suivante (exige
 * l'archivage, et qu'une année postérieure existe déjà).
 */
class AnneeArchivageTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function classeAvecEleve(string $nom): Classe
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => $nom]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => "Eleve de {$nom}", 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $classe;
    }

    public function test_archiver_echoue_si_une_classe_non_vide_na_pas_de_conseil_valide(): void
    {
        $this->classeAvecEleve('3ème A');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/annees-scolaires/{$this->annee->id}/archiver")
            ->assertStatus(422);

        $this->assertStringContainsString('3ème A', $reponse->json('message'));
        $this->assertNull($this->annee->fresh()->archivee_le);
    }

    public function test_archiver_reussit_quand_tout_est_traite(): void
    {
        $classe = $this->classeAvecEleve('3ème A');
        $classeVide = Classe::create(['school_id' => $this->school->id, 'nom' => 'Classe Vide']);

        $conseil = app(ConseilClasseService::class)->preparer($classe, $this->annee);
        app(ConseilClasseService::class)->valider($conseil, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/annees-scolaires/{$this->annee->id}/archiver")
            ->assertOk();

        $this->assertNotNull($this->annee->fresh()->archivee_le);
        $this->assertNotNull($classeVide); // classe vide : archivée sans conseil, ne bloque rien.
    }

    public function test_basculer_echoue_sans_archivage_prealable(): void
    {
        $suivante = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => false,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/annees-scolaires/{$suivante->id}/basculer")
            ->assertStatus(422);

        $this->assertFalse($suivante->fresh()->is_active);
    }

    public function test_basculer_echoue_si_lannee_choisie_nest_pas_posterieure(): void
    {
        $this->annee->update(['archivee_le' => now()]);
        $anterieure = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2024-2025',
            'date_debut' => '2024-09-01', 'date_fin' => '2025-07-31', 'is_active' => false,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/annees-scolaires/{$anterieure->id}/basculer")
            ->assertStatus(422);
    }

    public function test_basculer_active_lannee_suivante_une_fois_les_conditions_reunies(): void
    {
        $this->annee->update(['archivee_le' => now()]);
        $suivante = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => false,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/annees-scolaires/{$suivante->id}/basculer")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($suivante->fresh()->is_active);
        $this->assertFalse($this->annee->fresh()->is_active);
    }
}
