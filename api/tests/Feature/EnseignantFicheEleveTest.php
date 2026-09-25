<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
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
 * Sur la fiche d'un élève, l'enseignant consulte sa situation financière et
 * son transport en lecture seule (`eleves.situation`) — pour les seuls élèves
 * de ses classes, et sans accéder pour autant à la caisse. Son tableau de
 * bord détaille aussi ses indicateurs pédagogiques matière par matière.
 */
class EnseignantFicheEleveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    private Eleve $eleveAMoi;

    private Eleve $elevePasAMoi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $this->school->id, 'label_fr' => 'Enseignant'],
        );
        $fonction->synchroniserPermissions(['eleves.view', 'dashboard.view', 'eleves.situation']);

        $this->prof = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->prof = $this->prof->fresh();

        $classeAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème A']);
        $classePasAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème B']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        ClasseMatiere::create([
            'classe_id' => $classeAMoi->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $this->prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        $this->eleveAMoi = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classeAMoi->id,
            'matricule' => 'A1', 'nom_complet' => 'ELEVE A', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->elevePasAMoi = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classePasAMoi->id,
            'matricule' => 'B1', 'nom_complet' => 'ELEVE B', 'sexe' => 'F', 'statut' => 'actif',
        ]);
    }

    public function test_l_enseignant_voit_la_situation_financiere_et_le_transport_de_ses_eleves(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->eleveAMoi->id}/scolarite")
            ->assertOk();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->eleveAMoi->id}/transport")
            ->assertOk()
            ->assertJsonPath('data.id', $this->eleveAMoi->id);
    }

    public function test_l_enseignant_ne_voit_pas_les_eleves_hors_de_ses_classes(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->elevePasAMoi->id}/scolarite")
            ->assertNotFound();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->elevePasAMoi->id}/transport")
            ->assertNotFound();
    }

    public function test_la_situation_de_l_eleve_n_ouvre_pas_la_caisse(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/finance/insolvables')
            ->assertForbidden();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/bus/eleves')
            ->assertForbidden();
    }

    public function test_le_detail_des_indicateurs_pedagogiques_liste_les_matieres_de_l_enseignant(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/dashboard/indicateurs-pedagogiques')
            ->assertOk()
            ->assertJsonCount(1, 'data.matieres')
            ->assertJsonPath('data.matieres.0.classe', '6ème A')
            ->assertJsonPath('data.matieres.0.matiere', 'Mathématiques')
            ->assertJsonPath('data.matieres.0.taux_progression', 0);
    }
}
