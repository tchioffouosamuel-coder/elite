<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Modifications en masse : la fonction de plusieurs agents, et l'enseignant de
 * plusieurs affectations de matière.
 *
 * Ces deux gestes se faisaient ligne à ligne. Une matière transversale couvre
 * sept classes, et une reprise de fichier laisse des dizaines d'agents sans
 * fonction : le lot supprime autant d'allers-retours.
 */
class ModificationEnMasseTest extends TestCase
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

        $this->school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);

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

    private function fonction(string $label, ?School $school = null): FonctionReferentiel
    {
        return FonctionReferentiel::create([
            'school_id' => ($school ?? $this->school)->id,
            'label_fr' => $label,
        ]);
    }

    private function agent(string $nom, ?FonctionReferentiel $fonction = null, ?School $school = null): Personnel
    {
        return Personnel::create([
            'school_id' => ($school ?? $this->school)->id,
            'nom_complet' => $nom,
            'fonction_id' => $fonction?->id,
            'statut' => 'actif',
        ]);
    }

    private function classe(string $nom, ?School $school = null): Classe
    {
        $ecole = $school ?? $this->school;
        $annee = $ecole->is($this->school)
            ? $this->annee
            : AnneeScolaire::create([
                'school_id' => $ecole->id, 'libelle' => '2025-2026',
                'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
            ]);

        return Classe::create([
            'school_id' => $ecole->id, 'nom' => $nom,
        ]);
    }

    // ------------------------------------------------- Fonction en masse

    public function test_la_fonction_se_change_pour_plusieurs_agents(): void
    {
        $enseignant = $this->fonction('Enseignant');
        $agents = collect(['UN', 'DEUX', 'TROIS'])->map(fn ($n) => $this->agent($n));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/personnels/batch-fonction', [
                'ids' => $agents->pluck('id')->all(),
                'fonction_id' => $enseignant->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.modifies', 3);

        foreach ($agents as $agent) {
            $this->assertSame($enseignant->id, $agent->fresh()->fonction_id);
        }
    }

    /** Une fonction appartient à une école : elle ne peut pas franchir la frontière. */
    public function test_un_agent_d_une_autre_ecole_est_ignore(): void
    {
        $autre = School::create([
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
        ]);

        $enseignant = $this->fonction('Enseignant');
        $ici = $this->agent('ICI');
        $ailleurs = $this->agent('AILLEURS', null, $autre);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/personnels/batch-fonction', [
                'ids' => [$ici->id, $ailleurs->id],
                'fonction_id' => $enseignant->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.modifies', 1)
            ->assertJsonPath('data.ignores', 1);

        $this->assertSame($enseignant->id, $ici->fresh()->fonction_id);
        $this->assertNull($ailleurs->fresh()->fonction_id);
    }

    public function test_une_fonction_inconnue_est_refusee(): void
    {
        $agent = $this->agent('UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/personnels/batch-fonction', [
                'ids' => [$agent->id],
                'fonction_id' => 99999,
            ])
            ->assertStatus(422);
    }

    // ---------------------------------------------- Enseignant en masse

    /** Le cas de l'écran : une matière transversale, sept classes, un professeur. */
    public function test_l_enseignant_se_change_pour_plusieurs_affectations(): void
    {
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Citizenship']);
        $professeur = $this->agent('PROF CIVIQUE', $this->fonction('Enseignant'));

        $affectations = collect(['ACCOUNTING 1', 'AUTO MECHANICS 1', 'CLOTHING 1'])
            ->map(fn ($nom) => ClasseMatiere::create([
                'classe_id' => $this->classe($nom)->id,
                'matiere_id' => $matiere->id,
                'coefficient' => 2,
            ]));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-matieres/batch-enseignant', [
                'ids' => $affectations->pluck('id')->all(),
                'personnel_id' => $professeur->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.modifiees', 3);

        foreach ($affectations as $affectation) {
            $this->assertSame($professeur->id, $affectation->fresh()->personnel_id);
        }
    }

    /** Un enseignant nul détache : c'est le seul moyen de corriger une erreur d'affectation. */
    public function test_un_enseignant_nul_detache_les_affectations(): void
    {
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Sport']);
        $professeur = $this->agent('PROF SPORT', $this->fonction('Enseignant'));

        $affectation = ClasseMatiere::create([
            'classe_id' => $this->classe('6e A')->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $professeur->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-matieres/batch-enseignant', [
                'ids' => [$affectation->id],
                'personnel_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.modifiees', 1);

        $this->assertNull($affectation->fresh()->personnel_id);
    }

    /** Un enseignant ne peut pas prendre la classe d'une autre école. */
    public function test_une_classe_d_une_autre_ecole_est_ignoree(): void
    {
        $autre = School::create([
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
        ]);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais']);
        $professeur = $this->agent('PROF ANGLAIS', $this->fonction('Enseignant'));

        $ici = ClasseMatiere::create([
            'classe_id' => $this->classe('6e A')->id, 'matiere_id' => $matiere->id,
        ]);
        $ailleurs = ClasseMatiere::create([
            'classe_id' => $this->classe('CM2-A', $autre)->id, 'matiere_id' => $matiere->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-matieres/batch-enseignant', [
                'ids' => [$ici->id, $ailleurs->id],
                'personnel_id' => $professeur->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.modifiees', 1)
            ->assertJsonPath('data.ignorees', 1);

        $this->assertSame($professeur->id, $ici->fresh()->personnel_id);
        $this->assertNull($ailleurs->fresh()->personnel_id);
    }
}
