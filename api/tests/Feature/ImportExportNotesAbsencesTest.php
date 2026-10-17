<?php

namespace Tests\Feature;

use App\Models\AbsenceTrimestre;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
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
 * Points 17-18 de la punch-list : import/export Excel des notes, import
 * Excel des corrections d'assiduité — validation avant écriture, rapport
 * d'erreurs clair, jamais d'écrasement silencieux d'une valeur déjà saisie.
 */
class ImportExportNotesAbsencesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Trimestre $trimestre;

    private Sequence $sequence;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $this->sequence = Sequence::create([
            'trimestre_id' => $this->trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function enseignant(string $nom, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(['notes.view', 'notes.create', 'discipline.view', 'absences.saisir']);

        $user = User::create([
            'name' => $nom, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function eleveInscrit(Classe $classe, string $nom, string $matricule): Eleve
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => $nom, 'matricule' => $matricule, 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Preinscription::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->trimestre->annee_scolaire_id,
            'eleve_id' => $eleve->id,
            'type' => 'existant',
            'statut' => 'validee',
            'donnees_eleve' => [],
            'donnees_tuteurs' => [],
        ]);

        return $eleve->fresh();
    }

    private function classeMatiere(User $prof): array
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);
        $eleve = $this->eleveInscrit($classe, 'ELEVE UN', '26ELITES-0001');
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $attribution = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        return [$attribution, $eleve, $classe];
    }

    private function fichierNotes(string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('notes.csv', $contenu);
    }

    // ------------------------------------------------------------- Notes

    public function test_lexport_des_notes_se_telecharge(): void
    {
        [$attribution] = $this->classeMatiere($this->enseignant('Prof Export', 'prof.export@test.local'));

        Excel::fake();
        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/classe-matieres/{$attribution->id}/notes/export?sequence_id={$this->sequence->id}")
            ->assertOk();
        Excel::assertDownloaded(
            'notes-6e-a-mathematiques-sequence-1.xlsx',
            fn ($export) => $export->collection()->count() === 1,
        );
    }

    public function test_le_modele_des_notes_est_vide(): void
    {
        [$attribution, $eleve] = $this->classeMatiere($this->enseignant('Prof Modele', 'prof.modele@test.local'));
        Note::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 18,
        ]);

        Excel::fake();
        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/classe-matieres/{$attribution->id}/notes/modele?sequence_id={$this->sequence->id}")
            ->assertOk();
        Excel::assertDownloaded(
            'modele-notes-6e-a-mathematiques.xlsx',
            fn ($export) => $export->collection()->first()[2] === null,
        );
    }

    public function test_limport_valide_cree_les_notes(): void
    {
        [$attribution, $eleve] = $this->classeMatiere($this->enseignant('Prof Import', 'prof.import@test.local'));

        $this->actingAs($this->admin, 'sanctum')
            ->post("/api/v1/classe-matieres/{$attribution->id}/notes/import", [
                'sequence_id' => $this->sequence->id,
                'file' => $this->fichierNotes("matricule,note\n{$eleve->matricule},15\n"),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.confirmation_requise', false);

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence->id, 'valeur' => 15,
        ]);
    }

    public function test_une_ligne_invalide_est_rapportee_sans_bloquer_les_autres(): void
    {
        [$attribution, $eleve] = $this->classeMatiere($this->enseignant('Prof Erreurs', 'prof.erreurs@test.local'));
        $autreEleve = $this->eleveInscrit($attribution->classe, 'ELEVE DEUX', '26ELITES-0002');

        $contenu = "matricule,note\n{$eleve->matricule},15\n{$autreEleve->matricule},99\n";

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->post("/api/v1/classe-matieres/{$attribution->id}/notes/import", [
                'sequence_id' => $this->sequence->id,
                'file' => $this->fichierNotes($contenu),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.failed', 1);

        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 15]);
        $this->assertDatabaseMissing('notes', ['eleve_id' => $autreEleve->id]);
        $this->assertNotEmpty($reponse->json('data.errors'));
    }

    public function test_un_remplacement_de_note_exige_une_confirmation_explicite(): void
    {
        [$attribution, $eleve] = $this->classeMatiere($this->enseignant('Prof Conflit', 'prof.conflit@test.local'));
        Note::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 12,
        ]);

        $url = "/api/v1/classe-matieres/{$attribution->id}/notes/import";
        $corps = ['sequence_id' => $this->sequence->id, 'file' => $this->fichierNotes("matricule,note\n{$eleve->matricule},17\n")];

        $this->actingAs($this->admin, 'sanctum')->post($url, $corps, ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('errors.conflits.0.valeur_actuelle', 12)
            ->assertJsonPath('errors.conflits.0.valeur_fichier', 17);

        // Rien n'a été écrasé tant que la confirmation n'a pas été envoyée.
        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 12]);

        $corps['confirmer_remplacement'] = '1';
        $corps['file'] = $this->fichierNotes("matricule,note\n{$eleve->matricule},17\n");
        $this->post($url, $corps, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 17]);
    }

    public function test_un_enseignant_hors_perimetre_ne_peut_pas_importer(): void
    {
        $prof = $this->enseignant('Prof Hors Perimetre', 'prof.hors@test.local');
        $autreProf = $this->enseignant('Autre Prof', 'autre.prof@test.local');
        [$attribution, $eleve] = $this->classeMatiere($autreProf);

        $this->actingAs($prof, 'sanctum')
            ->post("/api/v1/classe-matieres/{$attribution->id}/notes/import", [
                'sequence_id' => $this->sequence->id,
                'file' => $this->fichierNotes("matricule,note\n{$eleve->matricule},15\n"),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('notes', 0);
    }

    // --------------------------------------------------------- Assiduité

    private function classeAvecEleve(User $prof): array
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '5e A']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais', 'statut' => 'actif']);
        ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);
        $eleve = $this->eleveInscrit($classe, 'ELEVE ASSIDU', '26ELITES-0003');

        return [$classe, $eleve];
    }

    private function fichierAbsences(string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('absences.csv', $contenu);
    }

    public function test_le_modele_dabsences_liste_les_eleves_de_la_classe(): void
    {
        [$classe] = $this->classeAvecEleve($this->enseignant('SG Modele', 'sg.modele@test.local'));

        Excel::fake();
        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/classes/{$classe->id}/absences/modele")
            ->assertOk();
        Excel::assertDownloaded(
            'modele-absences-5e-a.xlsx',
            fn ($export) => $export->collection()->count() === 1,
        );
    }

    public function test_limport_dabsences_cree_la_correction(): void
    {
        [$classe, $eleve] = $this->classeAvecEleve($this->enseignant('SG Import', 'sg.import@test.local'));

        $this->actingAs($this->admin, 'sanctum')
            ->post("/api/v1/classes/{$classe->id}/absences/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichierAbsences("matricule,heures_justifiees,heures_non_justifiees\n{$eleve->matricule},2,1\n"),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('absence_trimestres', [
            'eleve_id' => $eleve->id, 'trimestre_id' => $this->trimestre->id,
            'heures_justifiees' => 2, 'heures_non_justifiees' => 1,
        ]);
    }

    public function test_un_remplacement_dabsence_exige_une_confirmation_explicite(): void
    {
        [$classe, $eleve] = $this->classeAvecEleve($this->enseignant('SG Conflit', 'sg.conflit@test.local'));
        AbsenceTrimestre::create([
            'eleve_id' => $eleve->id, 'trimestre_id' => $this->trimestre->id,
            'heures_justifiees' => 1, 'heures_non_justifiees' => 0,
        ]);

        $url = "/api/v1/classes/{$classe->id}/absences/import";
        $corps = [
            'trimestre_id' => $this->trimestre->id,
            'file' => $this->fichierAbsences("matricule,heures_justifiees,heures_non_justifiees\n{$eleve->matricule},0,5\n"),
        ];

        $this->actingAs($this->admin, 'sanctum')->post($url, $corps, ['Accept' => 'application/json'])
            ->assertStatus(409);
        $this->assertDatabaseHas('absence_trimestres', ['eleve_id' => $eleve->id, 'heures_non_justifiees' => 0]);

        $corps['confirmer_remplacement'] = '1';
        $corps['file'] = $this->fichierAbsences("matricule,heures_justifiees,heures_non_justifiees\n{$eleve->matricule},0,5\n");
        $this->post($url, $corps, ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseHas('absence_trimestres', ['eleve_id' => $eleve->id, 'heures_non_justifiees' => 5]);
    }

    public function test_un_enseignant_hors_perimetre_ne_peut_pas_importer_les_absences(): void
    {
        $prof = $this->enseignant('SG Hors Perimetre', 'sg.hors@test.local');
        $autreProf = $this->enseignant('Autre SG', 'autre.sg@test.local');
        [$classe, $eleve] = $this->classeAvecEleve($autreProf);

        $this->actingAs($prof, 'sanctum')
            ->post("/api/v1/classes/{$classe->id}/absences/import", [
                'trimestre_id' => $this->trimestre->id,
                'file' => $this->fichierAbsences("matricule,heures_justifiees,heures_non_justifiees\n{$eleve->matricule},2,1\n"),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('absence_trimestres', 0);
    }
}
