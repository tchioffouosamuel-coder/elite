<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseCompetence;
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
 * volets à répartir (cf. StoreAttributionCompetenceRequest::parAppreciation).
 * La validation l'a toujours permis, mais la colonne `notation` posée par la
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

    /**
     * La compétence ne porte plus de barème nulle part : il appartient à son
     * attribution à une classe. Sa création se contente donc de son identité,
     * en maternelle comme au primaire.
     */
    public function test_une_competence_se_cree_sans_notation_ni_repartition(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->maternelle->id,
                'label_fr' => 'Communiquer en anglais',
                'label_en' => 'Communicate in English',
                'ordre' => 1,
            ])
            ->assertCreated();

        $competence = Competence::sole();
        $this->assertSame($this->maternelle->id, $competence->school_id);
        $this->assertArrayNotHasKey('notation', $competence->getAttributes());
        $this->assertArrayNotHasKey('repartition_volets', $competence->getAttributes());
    }

    /**
     * La maternelle évalue par appréciation : son attribution se pose sans
     * barème, et la validation ne l'exige pas.
     */
    public function test_une_attribution_de_maternelle_se_pose_sans_notation(): void
    {
        $classe = Classe::create(['school_id' => $this->maternelle->id, 'nom' => 'Petite section']);
        $competence = Competence::create([
            'school_id' => $this->maternelle->id, 'label_fr' => 'Communiquer en anglais',
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/classes/{$classe->id}/competences", [
                'competence_ids' => [$competence->id],
                'notation' => null,
                'repartition_volets' => null,
            ])
            ->assertOk();

        $attribution = ClasseCompetence::where('classe_id', $classe->id)->sole();
        $this->assertNull($attribution->notation);
    }
}
