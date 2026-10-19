<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Point 17 de la punch-list, volet primaire/maternelle (absent du premier
 * passage, qui n'avait couvert que le secondaire) : export/modèle/import
 * Excel de la grille de notes par volets (oral/écrit/savoir-être), pour tout
 * le trimestre d'un coup.
 */
class ImportExportNotesPrimaireTest extends TestCase
{
    use RefreshDatabase;

    private Trimestre $trimestre;

    private Sequence $sequence1;

    private Sequence $sequence2;

    private User $admin;

    private function ecole(string $type): School
    {
        return School::create(['name' => 'Elites '.$type, 'code' => 'E'.strtoupper($type), 'type' => $type, 'is_active' => true]);
    }

    private function annee(School $school): AnneeScolaire
    {
        $annee = AnneeScolaire::create([
            'school_id' => $school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $this->trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $this->sequence1 = Sequence::create(['trimestre_id' => $this->trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);
        $this->sequence2 = Sequence::create(['trimestre_id' => $this->trimestre->id, 'libelle' => 'Séquence 2', 'ordre' => 2]);

        return $annee;
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function admin(School $school): User
    {
        $admin = User::create([
            'name' => 'Root', 'email' => 'root'.uniqid().'@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function titulaire(School $school, string $nom): User
    {
        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $school->id, 'label_fr' => 'Titulaire']);
        $user = User::create([
            'name' => $nom, 'email' => strtolower(str_replace(' ', '.', $nom)).uniqid().'@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function eleveInscrit(School $school, Classe $classe, string $nom, string $matricule): Eleve
    {
        $eleve = Eleve::create([
            'school_id' => $school->id, 'classe_id' => $classe->id,
            'nom_complet' => $nom, 'matricule' => $matricule, 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Preinscription::create([
            'school_id' => $school->id, 'annee_scolaire_id' => $this->trimestre->annee_scolaire_id,
            'eleve_id' => $eleve->id, 'type' => 'existant', 'statut' => 'validee',
            'donnees_eleve' => [], 'donnees_tuteurs' => [],
        ]);

        return $eleve->fresh();
    }

    private function fichier(string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('notes.csv', $contenu);
    }

    // --------------------------------------------------------- Primaire (note)

    public function test_export_des_notes_primaire(): void
    {
        $school = $this->ecole('primaire');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'CM2']);
        $this->eleveInscrit($school, $classe, 'ELEVE UN', '26PRIM-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Communiquer en français', 'statut' => 'actif']);
        $cc = ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => 40, 'statut' => 'actif',
            'repartition_volets' => ['oral' => 20, 'ecrit' => 15, 'savoir_etre' => 5],
        ]);

        Excel::fake();
        $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/classe-competences/{$cc->id}/notes-primaire/export?trimestre_id={$this->trimestre->id}")
            ->assertOk();
        Excel::assertDownloaded(
            'notes-cm2-communiquer-en-francais.xlsx',
            fn ($export) => in_array('Oral S1', $export->headings(), true) && in_array('Oral S2', $export->headings(), true),
        );
    }

    public function test_import_primaire_cree_les_notes_par_volet(): void
    {
        $school = $this->ecole('primaire');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'CM2']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26PRIM-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Communiquer en français', 'statut' => 'actif']);
        $cc = ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => 40, 'statut' => 'actif',
            'repartition_volets' => ['oral' => 20, 'ecrit' => 15, 'savoir_etre' => 5],
        ]);

        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,15,10,3,16,11,4\n";

        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.confirmation_requise', false);

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id,
            'sequence_id' => $this->sequence1->id, 'composante' => 'oral', 'valeur' => 15,
        ]);
        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id,
            'sequence_id' => $this->sequence2->id, 'composante' => 'savoir_etre', 'valeur' => 4,
        ]);
    }

    public function test_une_valeur_hors_bareme_du_volet_est_rapportee_sans_etre_ecrite(): void
    {
        $school = $this->ecole('primaire');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'CM2']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26PRIM-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Communiquer en français', 'statut' => 'actif']);
        // Barème 20 réparti également sur 3 volets ⇒ 6,67 chacun : 15 au oral est hors plafond.
        $cc = ClasseCompetence::create(['classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => 20, 'statut' => 'actif']);

        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,15,,,,,\n";

        $reponse = $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.failed', 1);

        $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id, 'composante' => 'oral']);
    }

    public function test_un_remplacement_primaire_exige_une_confirmation_explicite(): void
    {
        $school = $this->ecole('primaire');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'CM2']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26PRIM-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Communiquer en français', 'statut' => 'actif']);
        $cc = ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => 40, 'statut' => 'actif',
            'repartition_volets' => ['oral' => 20, 'ecrit' => 15, 'savoir_etre' => 5],
        ]);
        Note::create([
            'eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id,
            'sequence_id' => $this->sequence1->id, 'composante' => 'oral', 'valeur' => 10,
        ]);

        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,18,,,,,\n";

        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('errors.conflits.0.eleve_id', $eleve->id);

        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id, 'composante' => 'oral', 'valeur' => 10]);
    }

    // -------------------------------------------------------- Maternelle (appreciation)

    public function test_import_maternelle_resout_lappreciation_par_libelle(): void
    {
        $school = $this->ecole('maternelle');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'Petite Section']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26MAT-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Vivre ensemble', 'statut' => 'actif']);
        $cc = ClasseCompetence::create(['classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => null, 'statut' => 'actif']);
        $appreciation = Appreciation::create([
            'school_id' => $school->id, 'label_fr' => 'Acquis', 'label_en' => 'Acquired', 'emoji' => '🙂', 'couleur' => '#16a34a', 'ordre' => 1,
        ]);

        // Accents/casse volontairement différents : le rapprochement doit les ignorer.
        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,ACQUIS,,,,,\n";

        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $cc->id,
            'sequence_id' => $this->sequence1->id, 'composante' => 'oral', 'appreciation_id' => $appreciation->id,
        ]);
    }

    public function test_une_appreciation_inconnue_est_rapportee(): void
    {
        $school = $this->ecole('maternelle');
        $this->annee($school);
        $admin = $this->admin($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'Petite Section']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26MAT-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Vivre ensemble', 'statut' => 'actif']);
        $cc = ClasseCompetence::create(['classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => null, 'statut' => 'actif']);
        Appreciation::create([
            'school_id' => $school->id, 'label_fr' => 'Acquis', 'label_en' => 'Acquired', 'emoji' => '🙂', 'couleur' => '#16a34a', 'ordre' => 1,
        ]);

        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,Excellent,,,,,\n";

        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_un_enseignant_hors_perimetre_ne_peut_pas_importer(): void
    {
        $school = $this->ecole('primaire');
        $this->annee($school);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'CM2']);
        $eleve = $this->eleveInscrit($school, $classe, 'ELEVE UN', '26PRIM-0001');
        $competence = Competence::create(['school_id' => $school->id, 'label_fr' => 'Communiquer en français', 'statut' => 'actif']);
        $cc = ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id, 'notation' => 40, 'statut' => 'actif',
            'repartition_volets' => ['oral' => 20, 'ecrit' => 15, 'savoir_etre' => 5],
        ]);

        $autreTitulaire = $this->titulaire($school, 'Autre Titulaire');
        Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web'])->syncPermissions(['notes.view', 'notes.create']);
        $autreTitulaire->assignRole('enseignant');

        $csv = "matricule,nom complet,Oral S1,Ecrit S1,Savoir-etre S1,Oral S2,Ecrit S2,Savoir-etre S2\n"
            ."{$eleve->matricule},ELEVE UN,15,,,,,\n";

        $this->actingAs($autreTitulaire, 'sanctum')
            ->post("/api/v1/classe-competences/{$cc->id}/notes-primaire/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichier($csv),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
