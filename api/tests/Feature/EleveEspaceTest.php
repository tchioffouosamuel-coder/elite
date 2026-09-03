<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use App\Models\User;
use App\Services\CompteEleveService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Portail élève, périmètre — pendant des tests parent : chaque compte élève
 * ne doit jamais voir que sa propre fiche (cf. EleveAccess::assertMoi()),
 * jamais celle d'un camarade même dans la même classe.
 */
class EleveEspaceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'eleve', 'guard_name' => 'web'])
            ->givePermissionTo(['notes.view', 'annonces.view', 'discipline.view', 'infirmerie.view', 'emploi_du_temps.view', 'bulletins.view']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'Terminale D']);
    }

    private function eleveAvecCompte(string $matricule, string $nom): array
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'matricule' => $matricule, 'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $user = app(CompteEleveService::class)->assurer($eleve);
        $user->forceFill(['doit_changer_mot_de_passe' => false])->save();

        return [$eleve, $user];
    }

    /** `moi()` ne renvoie jamais que la fiche du compte connecté. */
    public function test_moi_renvoie_uniquement_sa_propre_fiche(): void
    {
        [$eleveA, $userA] = $this->eleveAvecCompte('ET-001', 'Eleve A');
        [$eleveB, $userB] = $this->eleveAvecCompte('ET-002', 'Eleve B');

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/eleve/moi')
            ->assertOk()
            ->assertJsonPath('data.id', $eleveA->id)
            ->assertJsonPath('data.matricule', 'ET-001');

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/eleve/moi')
            ->assertOk()
            ->assertJsonPath('data.id', $eleveB->id)
            ->assertJsonPath('data.matricule', 'ET-002');
    }

    /** Les absences d'un élève ne fuient jamais vers un autre compte de la même classe. */
    public function test_absences_sont_isolees_par_compte(): void
    {
        [$eleveA, $userA] = $this->eleveAvecCompte('ET-010', 'Eleve A');
        [, $userB] = $this->eleveAvecCompte('ET-011', 'Eleve B');

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 3]);

        $seance = Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id, 'classe_matiere_id' => $classeMatiere->id,
            'date_seance' => now()->subDay()->toDateString(), 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);

        Presence::create([
            'eleve_id' => $eleveA->id, 'seance_id' => $seance->id, 'statut' => 'absent', 'justifie' => false,
        ]);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/eleve/absences')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/eleve/absences')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Un compte `eleve` sans fiche liée (cas impossible en usage normal) reçoit un 404, pas une erreur serveur. */
    public function test_compte_eleve_sans_fiche_liee_recoit_un_404(): void
    {
        $orphelin = User::create([
            'school_id' => $this->school->id, 'name' => 'Orphelin', 'password' => 'password', 'is_active' => true,
        ]);
        $orphelin->assignRole('eleve');

        $this->actingAs($orphelin, 'sanctum')
            ->getJson('/api/v1/eleve/moi')
            ->assertNotFound();
    }

    /** Sans le rôle `eleve`, le portail élève reste fermé même à un compte par ailleurs valide. */
    public function test_un_compte_sans_le_role_eleve_est_refuse(): void
    {
        $personnel = User::create([
            'school_id' => $this->school->id, 'name' => 'Personnel', 'email' => 'personnel@elites.test',
            'password' => 'password', 'is_active' => true,
        ]);

        $this->actingAs($personnel, 'sanctum')
            ->getJson('/api/v1/eleve/moi')
            ->assertForbidden();
    }
}
