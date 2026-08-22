<?php

namespace Tests\Feature;

use App\Exports\MatiereExport;
use App\Imports\MatiereImport;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Departement;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Import et export du catalogue des matières.
 *
 * Le cas qui compte est l'aller-retour : un établissement ne saisit pas son
 * catalogue dans un gabarit vide, il exporte ce qu'il a, corrige au tableur et
 * réimporte. Si l'export ne se relit pas, l'import ne sert à rien.
 */
class MatiereImportExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Secondaire',
            'code' => 'ES',
            'type' => 'secondaire',
            'is_active' => true,
        ]);
    }

    private function classe(string $nom, ?string $sigle = null): Classe
    {
        $niveau = Niveau::firstOrCreate(
            ['code' => 'college'],
            ['name_fr' => 'Collège', 'name_en' => 'Secondary'],
        );

        $annee = AnneeScolaire::firstOrCreate(
            ['school_id' => $this->school->id, 'libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true],
        );

        return Classe::create([
            'school_id' => $this->school->id,
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $annee->id,
            'nom' => $nom,
            'sigle' => $sigle,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    private function importer(array $lignes, string $cycle = MatiereImport::CYCLE_SECONDAIRE): MatiereImport
    {
        $import = new MatiereImport($this->school->id, $cycle);
        $import->collection(collect($lignes)->map(fn(array $ligne) => collect($ligne)));

        return $import;
    }

    public function test_le_secondaire_importe_matiere_departement_et_affectation(): void
    {
        $classe = $this->classe('ACC F4');
        $enseignant = Personnel::create([
            'school_id' => $this->school->id,
            'nom_complet' => 'ALOMBAH GENEVIEVE NGWENYI EPSE AFESE',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);

        $import = $this->importer([[
            'nom' => 'Commerce',
            'departement' => 'Commerce et gestion',
            'classes' => 'ACC F4',
            'coefficient' => 2,
            'periodes' => 2,
            'enseignant' => 'ALOMBAH GENEVIEVE NGWENYI EPSE AFESE',
        ]]);

        $this->assertSame(1, $import->importedCount);
        $this->assertSame(1, $import->affectationsCount);

        $matiere = Matiere::where('nom', 'Commerce')->firstOrFail();
        $this->assertSame('Commerce et gestion', $matiere->departement->nom);

        $affectation = ClasseMatiere::where('matiere_id', $matiere->id)->firstOrFail();
        $this->assertSame($classe->id, $affectation->classe_id);
        $this->assertSame($enseignant->id, $affectation->personnel_id);
        $this->assertSame('2.0', (string) $affectation->coefficient);
        $this->assertSame(2, $affectation->quota_horaire);
    }

    public function test_les_classes_se_separent_au_point_virgule_et_non_au_tiret(): void
    {
        // Deux classes dont le nom contient lui-même un tiret : découper
        // dessus produirait quatre libellés introuvables au lieu de deux.
        $this->classe('Home eco-5');
        $this->classe('ACT F1-Marketing F1');

        $import = $this->importer([[
            'nom' => 'Marketing',
            'classes' => 'Home eco-5 ; ACT F1-Marketing F1',
        ]]);

        $this->assertSame(2, $import->affectationsCount);
        $this->assertSame([], $import->classesIntrouvables);
    }

    public function test_une_classe_inconnue_est_signalee_sans_etre_creee(): void
    {
        $import = $this->importer([[
            'nom' => 'Physique',
            'classes' => 'Classe fantome',
        ]]);

        $this->assertSame(1, $import->importedCount);
        $this->assertSame(0, $import->affectationsCount);
        $this->assertSame(['Classe fantome' => 1], $import->classesIntrouvables);
        $this->assertSame(0, Classe::count());
    }

    public function test_un_enseignant_inconnu_est_signale_et_l_affectation_reste_creee(): void
    {
        $this->classe('6e A');

        $import = $this->importer([[
            'nom' => 'Histoire',
            'classes' => '6e A',
            'enseignant' => 'Personne Inexistante',
        ]]);

        $this->assertSame(1, $import->affectationsCount);
        $this->assertSame(['Personne Inexistante' => 1], $import->enseignantsIntrouvables);
        $this->assertNull(ClasseMatiere::first()->personnel_id);
    }

    public function test_le_reimport_met_a_jour_au_lieu_de_dupliquer(): void
    {
        $this->classe('6e A');

        $lignes = [['nom' => 'Anglais', 'abreviation' => 'ANG', 'classes' => '6e A', 'coefficient' => 4]];

        $this->importer($lignes);
        $second = $this->importer($lignes);

        $this->assertSame(0, $second->importedCount);
        $this->assertSame(1, $second->updatedCount);
        $this->assertSame(1, Matiere::count());
        $this->assertSame(1, ClasseMatiere::count());
    }

    public function test_une_colonne_absente_du_fichier_n_efface_pas_la_valeur_existante(): void
    {
        $this->importer([['nom' => 'Anglais', 'abreviation' => 'ANG']]);

        // Second fichier sans la colonne « abreviation » : la valeur déjà
        // saisie doit survivre plutôt que d'être remise à vide.
        $this->importer([['nom' => 'Anglais', 'departement' => 'Langues']]);

        $matiere = Matiere::where('nom', 'Anglais')->firstOrFail();
        $this->assertSame('ANG', $matiere->abbreviation);
        $this->assertSame('Langues', $matiere->departement->nom);
    }

    public function test_le_modele_secondaire_peut_renseigner_le_nom_dans_nom_en(): void
    {
        $import = $this->importer([
            ['nom' => '', 'nom_en' => 'Accounting', 'abreviation' => 'ACC'],
            ['nom' => null, 'nom_en' => 'Business Mathematics'],
        ]);

        $this->assertSame(2, $import->importedCount);
        $this->assertSame(0, $import->ignoredCount);
        $this->assertSame(2, Matiere::count());

        $accounting = Matiere::where('nom', 'Accounting')->firstOrFail();
        $this->assertSame('Accounting', $accounting->nom_en);
        $this->assertSame('ACC', $accounting->abbreviation);
        $this->assertTrue(Matiere::where('nom', 'Business Mathematics')->exists());
    }

    public function test_les_lignes_sans_nom_de_matiere_sont_ignorees(): void
    {
        $import = $this->importer([
            ['nom' => 'Sport'],
            ['nom' => '', 'coefficient' => 3],
            ['nom' => null],
        ]);

        $this->assertSame(1, $import->importedCount);
        $this->assertSame(2, $import->ignoredCount);
    }

    /**
     * Au primaire, le fichier décrit des COMPÉTENCES et non des matières :
     * c'est la compétence qui porte le barème réparti par volet, depuis que
     * l'évaluation a quitté la matière.
     */
    public function test_le_primaire_deduit_le_bareme_des_volets(): void
    {
        $import = $this->importer([
            ['nom' => 'Lecture', 'oral' => 10, 'ecrit' => 20, 'savoir_etre' => 5, 'pratique' => 0],
            ['nom' => 'Dessin', 'oral' => 5, 'ecrit' => 5, 'savoir_etre' => 5, 'pratique' => 5],
        ], MatiereImport::CYCLE_PRIMAIRE);

        $this->assertSame(2, $import->importedCount);
        // Aucune matière créée : la ligne du fichier est une compétence.
        $this->assertSame(0, Matiere::count());

        $lecture = Competence::where('label_fr', 'Lecture')->firstOrFail();
        $this->assertSame(35, $lecture->notation);
        $this->assertFalse($lecture->evalue_pratique);
        $this->assertArrayNotHasKey('pratique', $lecture->repartition_volets);

        $dessin = Competence::where('label_fr', 'Dessin')->firstOrFail();
        $this->assertSame(20, $dessin->notation);
        $this->assertTrue($dessin->evalue_pratique);
        // Comparaison souple : la répartition transite en JSON, qui ne
        // distingue pas 5 de 5.0 — c'est la valeur qui compte, pas le type.
        $this->assertEquals(5, $dessin->repartition_volets['pratique']);
    }

    public function test_le_primaire_ignore_les_colonnes_d_affectation(): void
    {
        $this->classe('SIL-A');

        $import = $this->importer(
            [['nom' => 'Lecture', 'oral' => 10, 'ecrit' => 10, 'savoir_etre' => 0, 'classes' => 'SIL-A']],
            MatiereImport::CYCLE_PRIMAIRE,
        );

        $this->assertSame(0, $import->affectationsCount);
        $this->assertSame(0, ClasseMatiere::count());
    }

    public function test_l_export_se_relit_par_l_import(): void
    {
        $classeA = $this->classe('6e A');
        $this->classe('6e B');
        $enseignant = Personnel::create([
            'school_id' => $this->school->id,
            'nom_complet' => 'Jean Mbarga',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
        $departement = Departement::create(['school_id' => $this->school->id, 'nom' => 'Sciences']);

        $matiere = Matiere::create([
            'school_id' => $this->school->id,
            'departement_id' => $departement->id,
            'nom' => 'Mathématiques',
            'abbreviation' => 'MATH',
            'statut' => 'actif',
        ]);
        ClasseMatiere::create([
            'classe_id' => $classeA->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $enseignant->id,
            'coefficient' => 6,
            'quota_horaire' => 5,
            'statut' => 'actif',
        ]);
        // Matière sans aucune affectation : elle doit tout de même sortir.
        Matiere::create(['school_id' => $this->school->id, 'nom' => 'Musique', 'statut' => 'actif']);

        $lignes = (new MatiereExport($this->school->id))->collection();
        $entetes = (new MatiereExport($this->school->id))->headings();

        $this->assertCount(2, $lignes);

        // On rejoue l'export dans l'import, en passant les intitulés au slug
        // exactement comme le fait maatwebsite à la lecture du fichier.
        $slugs = array_map(fn(string $entete) => str_replace('-', '_', Str::slug($entete, '_')), $entetes);

        Matiere::query()->delete();
        ClasseMatiere::query()->delete();

        $import = $this->importer($lignes->map(fn(array $ligne) => array_combine($slugs, $ligne))->all());

        $this->assertSame(2, $import->importedCount);
        $this->assertSame(1, $import->affectationsCount);

        $relue = Matiere::where('nom', 'Mathématiques')->firstOrFail();
        $this->assertSame('MATH', $relue->abbreviation);
        $this->assertSame('Sciences', $relue->departement->nom);

        $affectation = ClasseMatiere::where('matiere_id', $relue->id)->firstOrFail();
        $this->assertSame($classeA->id, $affectation->classe_id);
        $this->assertSame($enseignant->id, $affectation->personnel_id);
        $this->assertSame(5, $affectation->quota_horaire);
    }

    public function test_l_export_est_telechargeable(): void
    {
        Excel::fake();

        Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais', 'statut' => 'actif']);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->get('/api/v1/matieres/export')
            ->assertOk();

        Excel::assertDownloaded('matieres.xlsx');
    }

    public function test_l_import_exige_un_cycle_connu(): void
    {
        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/matieres/import', ['cycle' => 'lycee'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user->fresh();
    }
}
