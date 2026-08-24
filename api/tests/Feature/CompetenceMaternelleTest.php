<?php

namespace Tests\Feature;

use App\Models\Competence;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Une compétence de maternelle s'évalue par appréciation, sans barème ni
 * volets à répartir (cf. StoreCompetenceRequest::parAppreciation). La
 * validation l'a toujours permis, mais la colonne `notation` posée par la
 * migration d'origine restait NOT NULL en base — toute création sans barème
 * pour une école de maternelle échouait donc en 500 plutôt qu'en succès.
 */
class CompetenceMaternelleTest extends TestCase
{
    use RefreshDatabase;

    private School $maternelle;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->maternelle = School::create(['name' => 'Elites Bilingual Nursery School', 'code' => 'EBNS', 'type' => 'maternelle', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->maternelle->id, 'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_une_competence_de_maternelle_se_cree_sans_notation_ni_repartition(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->maternelle->id,
                'label_fr' => 'Communiquer en anglais',
                'label_en' => 'Communicate in English',
                'ordre' => 1,
                'notation' => null,
                'repartition_volets' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.notation', null);

        $competence = Competence::sole();
        $this->assertSame($this->maternelle->id, $competence->school_id);
        $this->assertNull($competence->notation);
    }

    /** Le primaire, lui, garde l'exigence d'un barème. */
    public function test_le_primaire_exige_toujours_une_notation(): void
    {
        $primaire = School::create(['name' => 'Elites Primary School', 'code' => 'EPS', 'type' => 'primaire', 'is_active' => true]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $primaire->id,
                'label_fr' => 'Langage',
            ])
            ->assertStatus(422);
    }
}
