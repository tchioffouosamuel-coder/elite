<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le verrou des 15 minutes protège l'appel d'une réécriture tardive par
 * l'enseignant, mais le super admin doit pouvoir corriger une séance
 * verrouillée — c'est la correction que le message d'erreur promet sans
 * qu'aucun autre chemin ne l'implémente (cf. Seance::appelVerrouillePour()).
 * Le Surveillant Général, lui, n'a pas cette dérogation.
 */
class AppelVerrouilleSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private ClasseMatiere $classeMatiere;

    private Seance $seance;

    private User $superAdmin;

    private User $surveillantGeneral;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET3', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => '4e B', 'qr_token' => 'TOKEN-4EB',
        ]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'SVT', 'statut' => 'actif']);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $sgFonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Surveillant Général',
        ]);
        $sgFonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['surveillant_general']);
        $this->surveillantGeneral = User::create([
            'name' => 'SG', 'email' => 'sg@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->surveillantGeneral->id, 'fonction_id' => $sgFonction->id,
            'nom_complet' => 'SG', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->surveillantGeneral = $this->surveillantGeneral->fresh();

        $profFonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $profFonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);
        $prof = User::create([
            'name' => 'Prof SVT', 'email' => 'prof.svt@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $prof->id, 'fonction_id' => $profFonction->id,
            'nom_complet' => 'Prof SVT', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $prof = $prof->fresh();

        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->classeMatiere->id,
            'jour' => now()->dayOfWeekIso, 'heure_debut' => '00:00', 'heure_fin' => '23:59',
        ]);

        // Séance déjà déclarée puis verrouillée depuis longtemps.
        $this->seance = Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->classeMatiere->id, 'date_seance' => now()->toDateString(),
            'heure_debut' => '00:00', 'heure_fin' => '23:59', 'statut' => 'effectuee',
            'appel_verrouille_le' => now()->subMinutes(30),
        ]);
    }

    public function test_le_super_admin_peut_corriger_l_appel_d_une_seance_verrouillee(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/seances/{$this->seance->id}/appel")
            ->assertOk()
            ->assertJsonPath('data.verrouille', false);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/seances/{$this->seance->id}/appel", [
                'lignes' => [
                    ['eleve_id' => Eleve::first()->id, 'statut' => 'absent', 'motif' => 'maladie'],
                ],
            ])
            ->assertOk();
    }

    public function test_le_surveillant_general_ne_peut_pas_corriger_l_appel_verrouille(): void
    {
        $this->actingAs($this->surveillantGeneral, 'sanctum')
            ->getJson("/api/v1/seances/{$this->seance->id}/appel")
            ->assertOk()
            ->assertJsonPath('data.verrouille', true);

        $this->actingAs($this->surveillantGeneral, 'sanctum')
            ->postJson("/api/v1/seances/{$this->seance->id}/appel", [
                'lignes' => [
                    ['eleve_id' => Eleve::first()->id, 'statut' => 'absent', 'motif' => 'maladie'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_le_super_admin_peut_declarer_ma_journee_sur_une_seance_verrouillee(): void
    {
        $reponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/ma-journee/{$this->classeMatiere->id}")
            ->assertOk();
        $this->assertFalse($reponse->json('data.seance.verrouille'));

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", [
                'lecons' => [],
                'appel' => [],
            ])
            ->assertOk();
    }
}
