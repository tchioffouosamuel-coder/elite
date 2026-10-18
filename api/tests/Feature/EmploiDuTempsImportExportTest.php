<?php

namespace Tests\Feature;

use App\Exports\EmploiDuTempsExport;
use App\Imports\EmploiDuTempsImport;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Services\EmploiDuTempsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import/export xlsx de l'emploi du temps d'une classe — le fichier produit
 * par l'export doit se réimporter tel quel, et l'import doit respecter les
 * mêmes garde-fous que la saisie manuelle (chevauchement, quota horaire).
 */
class EmploiDuTempsImportExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EDT', 'type' => 'secondaire', 'is_active' => true]);
        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-A']);
    }

    private function fichier(array $lignes): UploadedFile
    {
        $feuille = (new Spreadsheet)->getActiveSheet();

        $feuille->fromArray([
            ['Jour', 'Heure debut', 'Heure fin', 'Matiere', 'Enseignant', 'Salle', 'Classes associees'],
            ...$lignes,
        ], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), 'edt') . '.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        return new UploadedFile($chemin, 'edt.xlsx', null, null, true);
    }

    private function import(): EmploiDuTempsImport
    {
        return new EmploiDuTempsImport($this->classe, app(EmploiDuTempsService::class));
    }

    public function test_importe_un_creneau_et_cree_l_affectation_manquante(): void
    {
        Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'FOKO PIERRE', 'sexe' => 'M', 'statut' => 'actif']);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '10:00', 'Mathématiques', 'FOKO PIERRE', 'B12', null],
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertCount(0, $import->erreurs);

        $creneau = EmploiDuTemps::where('classe_id', $this->classe->id)->with('classeMatiere')->firstOrFail();
        $this->assertSame(1, $creneau->jour);
        $this->assertSame('08:00', substr((string) $creneau->heure_debut, 0, 5));
        $this->assertSame('10:00', substr((string) $creneau->heure_fin, 0, 5));
        $this->assertSame('B12', $creneau->salle);
        $this->assertSame('FOKO PIERRE', $creneau->classeMatiere->enseignant->nom_complet);
    }

    public function test_une_matiere_hors_catalogue_est_creee_et_ses_creneaux_sont_importes_sans_doublon(): void
    {
        Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '10:00', 'Matière inconnue', null, null, null],
            ['Mardi', '08:00', '10:00', 'Mathématiques', null, null, null],
            ['Mercredi', '08:00', '10:00', 'matiere inconnue', null, null, null],
            ['Jeudi', '08:00', '10:00', 'MATHEMATIQUES', null, null, null],
        ]));

        $this->assertSame(4, $import->importedCount);
        $this->assertSame([], $import->matieresIntrouvables);
        $this->assertSame([], $import->erreurs);
        $this->assertSame(2, Matiere::where('school_id', $this->school->id)->count());

        $matiere = Matiere::where('school_id', $this->school->id)->where('nom', 'Matière inconnue')->firstOrFail();
        $affectation = ClasseMatiere::where('classe_id', $this->classe->id)->where('matiere_id', $matiere->id)->sole();
        $this->assertSame(2, EmploiDuTemps::where('classe_matiere_id', $affectation->id)->count());

        $reimport = $this->import();
        Excel::import($reimport, $this->fichier([
            ['Vendredi', '08:00', '10:00', 'MATIERE INCONNUE', null, null, null],
        ]));

        $this->assertSame(1, $reimport->importedCount);
        $this->assertSame(2, Matiere::where('school_id', $this->school->id)->count());
        $this->assertSame(3, EmploiDuTemps::where('classe_matiere_id', $affectation->id)->count());
    }

    public function test_une_matiere_presente_uniquement_dans_une_autre_ecole_est_creee_dans_l_ecole_cible(): void
    {
        $autreEcole = School::create(['name' => 'Autre école', 'code' => 'AUT', 'type' => 'maternelle', 'is_active' => true]);
        $autreMatiere = Matiere::create(['school_id' => $autreEcole->id, 'nom' => 'WELCOME']);
        $enseignant = Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'FOKO PIERRE', 'sexe' => 'M', 'statut' => 'actif']);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '09:00', 'WELCOME', 'FOKO PIERRE', null, null],
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertSame([], $import->erreurs);
        $matiere = Matiere::where('school_id', $this->school->id)->where('nom', 'WELCOME')->sole();
        $this->assertNotSame($autreMatiere->id, $matiere->id);
        $creneau = EmploiDuTemps::where('classe_id', $this->classe->id)->with('classeMatiere')->sole();
        $this->assertSame($matiere->id, $creneau->classeMatiere->matiere_id);
        $this->assertSame($enseignant->id, $creneau->classeMatiere->personnel_id);
        $this->assertSame(1, Matiere::where('school_id', $autreEcole->id)->count());
    }

    public function test_un_horaire_invalide_ne_cree_pas_de_matiere(): void
    {
        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '10:00', '08:00', 'Matière inconnue', null, null, null],
        ]));

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->erreurs);
        $this->assertSame('Jour ou horaire invalide.', $import->erreurs[0]['message']);
        $this->assertSame(0, Matiere::where('school_id', $this->school->id)->count());
    }

    public function test_un_creneau_qui_chevauche_un_existant_est_ignore(): void
    {
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'classe_matiere_id' => $classeMatiere->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);

        $autreMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Physique']);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '09:00', '11:00', 'Physique', null, null, null],
        ]));

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->erreurs);
        $this->assertSame(1, EmploiDuTemps::where('classe_id', $this->classe->id)->count());
    }

    public function test_un_quota_horaire_depasse_est_ignore(): void
    {
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'quota_horaire' => 2]);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '11:00', 'Mathématiques', null, null, null],
        ]));

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->erreurs);
    }

    public function test_les_classes_associees_installent_un_tronc_commun(): void
    {
        $associee = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-B']);
        Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);

        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '10:00', 'Mathématiques', null, null, 'F3-B'],
        ]));

        $creneau = EmploiDuTemps::where('classe_id', $this->classe->id)->with('classesAssociees')->firstOrFail();
        $this->assertSame([$associee->id], $creneau->classesAssociees->pluck('id')->all());
    }

    public function test_une_ligne_sans_matiere_est_ignoree_silencieusement(): void
    {
        $import = $this->import();
        Excel::import($import, $this->fichier([
            ['Lundi', '08:00', '10:00', null, null, null, null],
        ]));

        $this->assertSame(0, $import->importedCount);
        $this->assertSame(1, $import->ignoredCount);
        $this->assertCount(0, $import->erreurs);
    }

    // -------------------------------------------------------------- Export

    public function test_l_export_ne_porte_que_les_creneaux_portes_par_la_classe(): void
    {
        $associee = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-B']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $enseignant = Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'FOKO PIERRE', 'sexe' => 'M', 'statut' => 'actif']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $enseignant->id,
        ]);
        $creneau = EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'classe_matiere_id' => $classeMatiere->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'salle' => 'B12',
        ]);
        $creneau->classesAssociees()->sync([$associee->id]);

        // Un créneau porté par l'AUTRE classe ne doit pas ressortir sur cet export.
        $autreMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Physique']);
        $autreClasseMatiere = ClasseMatiere::create(['classe_id' => $associee->id, 'matiere_id' => $autreMatiere->id]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $associee->id,
            'classe_matiere_id' => $autreClasseMatiere->id,
            'jour' => 2,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);

        $lignes = (new EmploiDuTempsExport($this->classe->fresh()))->collection();

        $this->assertCount(1, $lignes);
        $this->assertSame(['Lundi', '08:00', '10:00', 'Mathématiques', 'FOKO PIERRE', 'B12', 'F3-B'], $lignes->first());
    }

    /**
     * Le fichier produit par l'export se réimporte tel quel — c'est tout
     * l'intérêt du round-trip. Reproduit un usage réel : la grille est vidée
     * puis rechargée depuis le fichier corrigé, plutôt que réimportée
     * par-dessus elle-même (ce qui chevaucherait systématiquement l'original).
     */
    public function test_le_fichier_exporte_se_reimporte_a_l_identique(): void
    {
        $associee = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-B']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id]);
        $creneau = EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'classe_matiere_id' => $classeMatiere->id,
            'jour' => 3,
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'salle' => 'Labo',
        ]);
        $creneau->classesAssociees()->sync([$associee->id]);

        $lignes = (new EmploiDuTempsExport($this->classe->fresh()))->collection()->map(fn($l) => array_values($l))->all();

        // Reproduit ce que fait ExcelController::export() -> réimport, sans passer par le disque.
        $feuille = (new Spreadsheet)->getActiveSheet();
        $feuille->fromArray([['Jour', 'Heure debut', 'Heure fin', 'Matiere', 'Enseignant', 'Salle', 'Classes associees'], ...$lignes], null, 'A1');
        $chemin = tempnam(sys_get_temp_dir(), 'edt') . '.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        // Grille vidée avant réimport : sans quoi le créneau réimporté
        // chevaucherait systématiquement l'original, associée comprise.
        $creneau->delete();

        $import = new EmploiDuTempsImport($this->classe, app(EmploiDuTempsService::class));
        Excel::import($import, new UploadedFile($chemin, 'edt.xlsx', null, null, true));

        $this->assertSame(1, $import->importedCount);
        $copie = EmploiDuTemps::where('classe_id', $this->classe->id)->with('classesAssociees')->firstOrFail();
        $this->assertSame(3, $copie->jour);
        $this->assertSame('14:00', substr((string) $copie->heure_debut, 0, 5));
        $this->assertSame('Labo', $copie->salle);
        $this->assertSame([$associee->id], $copie->classesAssociees->pluck('id')->all());
    }
}
