<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Departement;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\SousSysteme;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Circuit HTTP du suivi transverse prévu/réalisé — le pendant admin de
 * `heuresCouverture()`, mais ventilé par période et pour tout le personnel.
 */
class SuiviActiviteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $enseignant;

    private ClasseMatiere $classeMatiere;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'College', 'ordre' => 1]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'nom' => '6ème A',
        ]);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);

        $this->enseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SONG ERIC MUNYAM', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'personnel_id' => $this->enseignant->id,
            'statut' => 'actif',
        ]);

        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => '2026-09-02', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'effectuee',
        ]);
        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => '2026-09-03', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'prevue',
        ]);
        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => '2026-09-04', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'annulee',
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_le_suivi_cumule_les_heures_prevues_et_realisees_par_jour(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&granularite=jour')
            ->assertOk();

        $ligne = $response->json('data')[0];

        $this->assertSame($this->enseignant->id, $ligne['personnel_id']);
        $this->assertEquals(4.0, $ligne['totaux']['heures_prevues']);
        $this->assertEquals(1.0, $ligne['totaux']['heures_realisees']);
        $this->assertCount(3, $ligne['periodes']);
    }

    public function test_le_filtre_personnel_id_restreint_le_resultat(): void
    {
        $autre = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'AUTRE ENSEIGNANT', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&personnel_id={$autre->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_le_titulaire_du_primaire_apparait_sans_etre_nomme_sur_lclasse_matiere(): void
    {
        $titulaire = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'TITULAIRE CP', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $classePrimaire = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CP', 'titulaire_id' => $titulaire->id,
        ]);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Lecture']);

        $classeMatierePrimaire = ClasseMatiere::create([
            'classe_id' => $classePrimaire->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
        ]);

        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $classePrimaire->id, 'classe_matiere_id' => $classeMatierePrimaire->id,
            'date_seance' => '2026-09-02', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'effectuee',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&personnel_id={$titulaire->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $ligne = $response->json('data')[0];
        $this->assertSame($titulaire->id, $ligne['personnel_id']);
        $this->assertEquals(1.0, $ligne['totaux']['heures_realisees']);
    }

    public function test_le_filtre_sous_systeme_id_restreint_aux_classes_de_cette_section(): void
    {
        $anglophone = SousSysteme::create(['school_id' => $this->school->id, 'code' => 'ANG', 'nom' => 'Anglophone']);

        $autreEnseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'AUTRE ENSEIGNANT', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $classeAnglophone = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'Form 1', 'sous_systeme_id' => $anglophone->id,
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'English']);
        $classeMatiereAnglophone = ClasseMatiere::create([
            'classe_id' => $classeAnglophone->id, 'matiere_id' => $matiere->id, 'personnel_id' => $autreEnseignant->id,
            'statut' => 'actif',
        ]);
        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $classeAnglophone->id, 'classe_matiere_id' => $classeMatiereAnglophone->id,
            'date_seance' => '2026-09-02', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'effectuee',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&sous_systeme_id={$anglophone->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($autreEnseignant->id, $response->json('data')[0]['personnel_id']);
    }

    public function test_le_filtre_departement_id_restreint_au_departement_de_lenseignant(): void
    {
        $sciences = Departement::create(['school_id' => $this->school->id, 'nom' => 'Sciences']);
        $this->enseignant->update(['departement_id' => $sciences->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&departement_id={$sciences->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($this->enseignant->id, $response->json('data')[0]['personnel_id']);

        $autreDepartement = Departement::create(['school_id' => $this->school->id, 'nom' => 'Lettres']);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/personnels/suivi-activite?date_debut=2026-09-01&date_fin=2026-09-30&departement_id={$autreDepartement->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
