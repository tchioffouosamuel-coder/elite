<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotesEnseignantClasseurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function contexte(string $type): array
    {
        $school = School::create(['name' => 'Elites '.$type, 'code' => uniqid(), 'type' => $type, 'is_active' => true]);
        $annee = AnneeScolaire::create(['school_id' => $school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true]);
        $trimestre = Trimestre::create(['annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true]);
        $s1 = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);
        $s2 = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 2', 'ordre' => 2]);
        $fonction = FonctionReferentiel::create(['school_id' => $school->id, 'label_fr' => 'Enseignant']);
        $prof = Personnel::create(['school_id' => $school->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof Test', 'statut' => 'actif']);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'Classe A', 'titulaire_id' => $prof->id]);
        $eleve = Eleve::create(['school_id' => $school->id, 'classe_id' => $classe->id, 'nom_complet' => 'ELEVE UN',
            'matricule' => '0001', 'sexe' => 'F', 'statut' => 'actif']);
        $this->inscrireAnneeActive($eleve);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Root', 'email' => uniqid().'@test.local',
            'password' => 'password', 'is_active' => true]);
        $admin->assignRole('super_admin');

        return compact('school', 'annee', 'trimestre', 's1', 's2', 'prof', 'classe', 'eleve', 'admin');
    }

    private function matiere(array $c, string $nom = 'Mathématiques'): ClasseMatiere
    {
        $matiere = Matiere::create(['school_id' => $c['school']->id, 'nom' => $nom, 'statut' => 'actif']);

        return ClasseMatiere::create(['classe_id' => $c['classe']->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $c['prof']->id, 'statut' => 'actif']);
    }

    private function competence(array $c, string $nom, bool $pratique = false): ClasseCompetence
    {
        $competence = Competence::create(['school_id' => $c['school']->id, 'label_fr' => $nom, 'statut' => 'actif']);

        return ClasseCompetence::create(['classe_id' => $c['classe']->id, 'competence_id' => $competence->id,
            'notation' => 40, 'statut' => 'actif', 'evalue_pratique' => $pratique,
            'repartition_volets' => $c['school']->type === 'maternelle' ? null
                : ['oral' => 20, 'ecrit' => 10, 'savoir_etre' => 5, 'pratique' => 5]]);
    }

    private function params(array $c): array
    {
        return $c['school']->type === 'secondaire' ? ['sequence_id' => $c['s1']->id] : ['trimestre_id' => $c['trimestre']->id];
    }

    private function classeur(array $c, string $action = 'modele'): Spreadsheet
    {
        $response = $this->actingAs($c['admin'], 'sanctum')->get(
            "/api/v1/personnels/{$c['prof']->id}/notes/{$action}?".http_build_query($this->params($c)),
        )->assertOk();

        return IOFactory::load($response->baseResponse->getFile()->getPathname());
    }

    private function fichier(Spreadsheet $book): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'notes-enseignant-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('notes.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function importer(array $c, Spreadsheet $book, bool $confirmer = false)
    {
        return $this->actingAs($c['admin'], 'sanctum')->postJson("/api/v1/personnels/{$c['prof']->id}/notes/import", [
            ...$this->params($c), 'file' => $this->fichier($book), 'confirmer_remplacement' => $confirmer,
        ]);
    }

    public function test_secondaire_un_classeur_multifeuille_fait_laller_retour_avec_les_zeros(): void
    {
        $c = $this->contexte('secondaire');
        $m1 = $this->matiere($c, 'Mathématiques / options [longues] : première');
        $m2 = $this->matiere($c, 'Mathématiques / options [longues] : deuxième');
        $book = $this->classeur($c);
        $this->assertCount(2, $book->getAllSheets());
        $this->assertNotSame($book->getSheet(0)->getTitle(), $book->getSheet(1)->getTitle());
        $this->assertLessThanOrEqual(31, mb_strlen($book->getSheet(0)->getTitle()));
        $this->assertSame('0001', $book->getSheet(0)->getCell('A2')->getValue());
        $this->assertNull($book->getSheet(0)->getCell('C2')->getValue());
        $book->getSheet(0)->setCellValue('C2', 0);
        $book->getSheet(1)->setCellValue('C2', 18.5);
        $this->importer($c, $book)->assertOk()->assertJsonPath('data.imported', 2);
        $this->assertDatabaseHas('notes', ['classe_matiere_id' => $m1->id, 'valeur' => 0]);
        $this->assertDatabaseHas('notes', ['classe_matiere_id' => $m2->id, 'valeur' => 18.5]);
        $export = $this->classeur($c, 'export');
        $this->assertEquals(18.5, $export->getSheet(1)->getCell('C2')->getValue());
    }

    public function test_le_modele_secondaire_colorie_les_colonnes_et_alterne_les_lignes(): void
    {
        $c = $this->contexte('secondaire');
        $this->matiere($c);
        $this->inscrireAnneeActive(Eleve::create([
            'school_id' => $c['school']->id, 'classe_id' => $c['classe']->id, 'nom_complet' => 'ELEVE DEUX',
            'matricule' => '0002', 'sexe' => 'M', 'statut' => 'actif',
        ]));

        $sheet = $this->classeur($c)->getSheet(0);
        $this->assertNotSame($sheet->getStyle('A1')->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle('B1')->getFill()->getStartColor()->getRGB());
        $this->assertNotSame($sheet->getStyle('B1')->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle('C1')->getFill()->getStartColor()->getRGB());
        $this->assertNotSame($sheet->getStyle('C2')->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle('C3')->getFill()->getStartColor()->getRGB());
    }

    public function test_primaire_les_competences_sont_des_blocs_de_colonnes_sur_la_meme_feuille(): void
    {
        $c = $this->contexte('primaire');
        $cc1 = $this->competence($c, 'Communiquer');
        $cc2 = $this->competence($c, 'Créer', true);
        $book = $this->classeur($c);
        $this->assertCount(1, $book->getAllSheets());
        $sheet = $book->getSheet(0);
        $this->assertSame("Communiquer [{$cc1->id}]", $sheet->getCell('C1')->getValue());
        $this->assertSame("Créer [{$cc2->id}]", $sheet->getCell('I1')->getValue());
        $this->assertContains('C1:H1', $sheet->getMergeCells());
        $this->assertContains('I1:P1', $sheet->getMergeCells());
        $this->assertSame('Pratique S2', $sheet->getCell('P2')->getValue());
        $sheet->setCellValue('C3', 15)->setCellValue('D3', 16)->setCellValue('I3', 19)->setCellValue('P3', 5);
        $this->importer($c, $book)->assertOk()->assertJsonPath('data.imported', 4);
        $this->assertDatabaseHas('notes', ['classe_competence_id' => $cc1->id, 'sequence_id' => $c['s2']->id, 'composante' => 'oral', 'valeur' => 16]);
        $this->assertDatabaseHas('notes', ['classe_competence_id' => $cc2->id, 'sequence_id' => $c['s2']->id, 'composante' => 'pratique', 'valeur' => 5]);
        $this->assertEquals(19, $this->classeur($c, 'export')->getSheet(0)->getCell('I3')->getValue());
    }

    public function test_le_modele_primaire_colorie_les_colonnes_et_alterne_les_lignes(): void
    {
        $c = $this->contexte('primaire');
        $this->competence($c, 'Communiquer');
        $this->inscrireAnneeActive(Eleve::create([
            'school_id' => $c['school']->id, 'classe_id' => $c['classe']->id, 'nom_complet' => 'ELEVE DEUX',
            'matricule' => '0002', 'sexe' => 'M', 'statut' => 'actif',
        ]));

        $sheet = $this->classeur($c)->getSheet(0);
        $this->assertNotSame($sheet->getStyle('C2')->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle('D2')->getFill()->getStartColor()->getRGB());
        $this->assertNotSame($sheet->getStyle('C3')->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle('C4')->getFill()->getStartColor()->getRGB());
        $this->assertContains('C1:H1', $sheet->getMergeCells());
    }

    public function test_maternelle_le_classeur_contient_et_importe_des_appreciations(): void
    {
        $c = $this->contexte('maternelle');
        $cc1 = $this->competence($c, 'Communiquer');
        $this->competence($c, 'Créer');
        $appreciation = Appreciation::create(['school_id' => $c['school']->id, 'label_fr' => 'Acquis',
            'couleur' => '#16a34a', 'ordre' => 1, 'statut' => 'actif']);
        $book = $this->classeur($c);
        $book->getSheet(0)->setCellValue('C3', 'Acquis')->setCellValue('I3', 'Acquis');
        $this->importer($c, $book)->assertOk()->assertJsonPath('data.imported', 2);
        $this->assertDatabaseHas('notes', ['classe_competence_id' => $cc1->id, 'appreciation_id' => $appreciation->id, 'valeur' => null]);
        $this->assertSame('Acquis', $this->classeur($c, 'export')->getSheet(0)->getCell('C3')->getValue());
    }

    public function test_un_conflit_sur_la_derniere_feuille_empeche_toute_ecriture_avant_confirmation(): void
    {
        $c = $this->contexte('secondaire');
        $m1 = $this->matiere($c);
        $m2 = $this->matiere($c, 'Français');
        Note::create(['classe_matiere_id' => $m2->id, 'eleve_id' => $c['eleve']->id,
            'sequence_id' => $c['s1']->id, 'composante' => 'unique', 'valeur' => 10]);
        $book = $this->classeur($c);
        $book->getSheet(0)->setCellValue('C2', 14);
        $book->getSheet(1)->setCellValue('C2', 18);
        $this->importer($c, $book)->assertStatus(409)->assertJsonPath('errors.conflits.0.valeur_actuelle', 10);
        $this->assertDatabaseMissing('notes', ['classe_matiere_id' => $m1->id]);
        $this->assertDatabaseHas('notes', ['classe_matiere_id' => $m2->id, 'valeur' => 10]);
        $this->importer($c, $book, true)->assertOk()->assertJsonPath('data.imported', 2);
        $this->assertDatabaseHas('notes', ['classe_matiere_id' => $m2->id, 'valeur' => 18]);
    }

    public function test_une_feuille_etrangere_est_refusee_sans_import_partiel(): void
    {
        $c = $this->contexte('secondaire');
        $this->matiere($c);
        $this->matiere($c, 'Français');
        $book = $this->classeur($c);
        $book->getSheet(0)->setCellValue('C2', 14);
        $book->getSheet(1)->setTitle('999999 Classe étrangère');
        $this->importer($c, $book)->assertUnprocessable();
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_les_cellules_invalides_sont_rapportees_sans_ecrire_de_note_hors_bareme(): void
    {
        $c = $this->contexte('primaire');
        $this->competence($c, 'Communiquer');
        $book = $this->classeur($c);
        $book->getSheet(0)->setCellValue('C3', 99)->setCellValue('D3', 16);
        $this->importer($c, $book)->assertOk()->assertJsonPath('data.failed', 1)->assertJsonPath('data.imported', 1);
        $this->assertDatabaseMissing('notes', ['valeur' => 99]);
    }

    public function test_une_competence_etrangere_est_refusee(): void
    {
        $c = $this->contexte('primaire');
        $this->competence($c, 'Communiquer');
        $book = $this->classeur($c);
        $book->getSheet(0)->setCellValue('C1', 'Autre compétence [999999]')->setCellValue('C3', 10);
        $this->importer($c, $book)->assertUnprocessable();
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_la_periode_et_lenseignant_restant_bornes_a_leur_ecole(): void
    {
        $c = $this->contexte('secondaire');
        $this->matiere($c);
        $autre = $this->contexte('secondaire');
        $this->actingAs($c['admin'], 'sanctum')->getJson("/api/v1/personnels/{$c['prof']->id}/notes/modele?sequence_id={$autre['s1']->id}")->assertNotFound();
        $this->actingAs($c['admin'], 'sanctum')->withHeader('X-School-Id', $c['school']->id)
            ->getJson("/api/v1/personnels/{$autre['prof']->id}/notes/modele?sequence_id={$autre['s1']->id}")->assertNotFound();
    }

    public function test_le_suivi_seul_nautorise_pas_limport_des_notes(): void
    {
        $c = $this->contexte('secondaire');
        $this->matiere($c);
        $book = $this->classeur($c);
        $user = User::create(['school_id' => $c['school']->id, 'name' => 'Lecture', 'email' => 'lecture@test.local',
            'password' => 'password', 'is_active' => true]);
        $user->givePermissionTo('pedagogie.suivi_saisies');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/personnels/{$c['prof']->id}/notes/import", [
            ...$this->params($c), 'file' => $this->fichier($book),
        ])->assertForbidden();
    }
}
