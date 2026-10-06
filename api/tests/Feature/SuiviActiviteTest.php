<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Departement;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\Seance;
use App\Models\Sequence;
use App\Models\SousSysteme;
use App\Models\Trimestre;
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

    private function connecterEnseignant(): User
    {
        $prof = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $prof->givePermissionTo('appel.saisir');
        $this->enseignant->update(['user_id' => $prof->id]);

        return $prof->fresh();
    }

    public function test_la_progression_personnelle_compte_les_lecons_et_les_heures_sur_cinq_periodes(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 13)->setTime(12, 0));
        $anneeId = AnneeScolaire::where('school_id', $this->school->id)->value('id');
        Trimestre::create([
            'annee_scolaire_id' => $anneeId, 'libelle' => 'T2', 'ordre' => 2,
            'date_debut' => '2027-01-05', 'date_fin' => '2027-03-25', 'is_active' => true,
        ]);
        $seances = [];
        foreach (['2027-01-13', '2027-01-12', '2027-01-01', '2027-02-01', '2027-05-01', '2026-09-02'] as $i => $date) {
            $faite = ! in_array($i, [3, 4], true);
            $seance = Seance::create([
                'school_id' => $this->school->id, 'classe_id' => $this->classeMatiere->classe_id,
                'classe_matiere_id' => $this->classeMatiere->id, 'date_seance' => $date,
                'heure_debut' => '10:00', 'heure_fin' => '11:00', 'statut' => $faite ? 'effectuee' : 'prevue',
            ]);
            $lecon = ProgressionItem::create([
                'classe_matiere_id' => $this->classeMatiere->id, 'type' => 'lecon',
                'titre' => "Lecon $i", 'ordre' => $i, 'date_prevue' => $date,
            ]);
            if ($faite) {
                $seance->lecons()->attach($lecon->id);
            }
            $seances[] = $seance;
        }
        // Validation repetee : une seule lecon faite sur le mois et l'annee.
        $seances[0]->lecons()->attach($seances[1]->lecons()->first()->id);
        ProgressionItem::create([
            'classe_matiere_id' => $this->classeMatiere->id, 'type' => 'chapitre',
            'titre' => 'Pas une lecon', 'ordre' => 10, 'date_prevue' => '2027-01-13',
        ]);
        $autre = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Autre', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $autreAffectation = ClasseMatiere::create([
            'classe_id' => $this->classeMatiere->classe_id,
            'matiere_id' => Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais'])->id,
            'personnel_id' => $autre->id, 'statut' => 'actif',
        ]);
        ProgressionItem::create([
            'classe_matiere_id' => $autreAffectation->id, 'type' => 'lecon',
            'titre' => 'Autre enseignant', 'ordre' => 1, 'date_prevue' => '2027-01-13',
        ]);
        Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classeMatiere->classe_id,
            'classe_matiere_id' => $autreAffectation->id, 'date_seance' => '2027-01-13',
            'heure_debut' => '08:00', 'heure_fin' => '14:00', 'statut' => 'effectuee',
        ]);

        $response = $this->actingAs($this->connecterEnseignant(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/ma-journee/couverture-periodes')->assertOk();

        foreach ([
            'jour' => [1, 2, 1, 1],
            'semaine' => [2, 2, 2, 2],
            'mois' => [3, 3, 3, 3],
            'trimestre' => [3, 2, 3, 2],
            'annee' => [6, 4, 10, 5],
        ] as $periode => [$prevues, $faites, $heuresPrevues, $heuresFaites]) {
            $response->assertJsonPath("data.$periode.lecons_prevues", $prevues)
                ->assertJsonPath("data.$periode.lecons_realisees", $faites);
            $this->assertEquals($heuresPrevues, $response->json("data.$periode.heures_prevues"));
            $this->assertEquals($heuresFaites, $response->json("data.$periode.heures_realisees"));
        }
        $response->assertJsonPath('data.trimestre.date_debut', '2027-01-05')
            ->assertJsonPath('data.annee.date_debut', '2026-09-01')
            ->assertJsonPath('data.annee.date_fin', '2027-07-31');
    }

    public function test_sans_trimestre_la_progression_ne_fabrique_pas_de_trimestre_civil(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 2)->setTime(12, 0));
        $this->classeMatiere->update(['personnel_id' => null]);
        $this->classeMatiere->classe->update(['titulaire_id' => $this->enseignant->id]);
        $lecon = ProgressionItem::create([
            'classe_matiere_id' => $this->classeMatiere->id, 'type' => 'lecon',
            'titre' => 'Lecture', 'ordre' => 1, 'date_prevue' => '2026-09-02', 'date_realisee' => '2026-09-02',
        ]);
        Seance::where('statut', 'effectuee')->first()->lecons()->attach($lecon->id);

        $this->actingAs($this->connecterEnseignant(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/ma-journee/couverture-periodes')->assertOk()
            ->assertJsonPath('data.trimestre', null)
            ->assertJsonPath('data.jour.lecons_realisees', 1)
            ->assertJsonPath('data.jour.lecons_prevues', 1);
    }

    public function test_la_progression_sans_personnel_retourne_cinq_periodes_vides(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/ma-journee/couverture-periodes')->assertOk()
            ->assertJsonPath('data.trimestre.lecons_prevues', 0)
            ->assertJsonPath('data.annee.lecons_realisees', 0);
    }

    public function test_les_lecons_non_datees_restent_prevues_sur_leur_periode_scolaire(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 2)->setTime(12, 0));
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => AnneeScolaire::where('school_id', $this->school->id)->value('id'),
            'libelle' => 'T1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20',
        ]);
        $sequence = Sequence::create([
            'trimestre_id' => $trimestre->id, 'libelle' => 'S1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-10-20',
        ]);
        foreach ([$sequence->id, null] as $i => $sequenceId) {
            ProgressionItem::create([
                'classe_matiere_id' => $this->classeMatiere->id, 'type' => 'lecon',
                'titre' => "Programme $i", 'ordre' => $i, 'sequence_id' => $sequenceId,
            ]);
        }

        $this->actingAs($this->connecterEnseignant(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/ma-journee/couverture-periodes')->assertOk()
            ->assertJsonPath('data.jour.lecons_prevues', 0)
            ->assertJsonPath('data.semaine.lecons_prevues', 0)
            ->assertJsonPath('data.mois.lecons_prevues', 0)
            ->assertJsonPath('data.trimestre.lecons_prevues', 1)
            ->assertJsonPath('data.annee.lecons_prevues', 2);
    }

    public function test_la_progression_ne_compte_pas_les_affectations_dune_autre_ecole(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 2)->setTime(12, 0));
        $autreEcole = School::create(['name' => 'Autre ecole', 'code' => 'AUT', 'type' => 'secondaire', 'is_active' => true]);
        $classe = Classe::create(['school_id' => $autreEcole->id, 'nom' => '6e']);
        $matiere = Matiere::create(['school_id' => $autreEcole->id, 'nom' => 'Maths']);
        $affectation = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $this->enseignant->id, 'statut' => 'actif',
        ]);
        ProgressionItem::create([
            'classe_matiere_id' => $affectation->id, 'type' => 'lecon', 'titre' => 'Autre ecole',
            'ordre' => 1, 'date_prevue' => '2026-09-02', 'date_realisee' => '2026-09-02',
        ]);
        Seance::create([
            'school_id' => $autreEcole->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $affectation->id,
            'date_seance' => '2026-09-02', 'heure_debut' => '08:00', 'heure_fin' => '12:00', 'statut' => 'effectuee',
        ]);
        $response = $this->actingAs($this->connecterEnseignant(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/ma-journee/couverture-periodes')->assertOk()
            ->assertJsonPath('data.jour.lecons_prevues', 0)
            ->assertJsonPath('data.jour.lecons_realisees', 0);
        $this->assertEquals(1, $response->json('data.jour.heures_prevues'));
    }
}
