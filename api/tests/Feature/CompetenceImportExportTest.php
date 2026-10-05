<?php

namespace Tests\Feature;

use App\Exports\CompetenceExport;
use App\Imports\CompetenceImport;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\School;
use App\Services\CompetenceAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import et export du référentiel des compétences.
 *
 * Le fichier tient une ligne par compétence ET par classe, parce que le barème
 * appartient au couple : la même compétence pèse 20 au CM2 et 10 au CP. Le cas
 * qui compte est l'aller-retour — un établissement exporte ce qu'il a, corrige
 * au tableur et réimporte ; si l'export ne se relit pas, l'import ne sert à
 * rien.
 */
class CompetenceImportExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
        ]);
    }

    private function classe(string $nom): Classe
    {
        $niveau = Niveau::firstOrCreate(
            ['code' => 'primaire'],
            ['name_fr' => 'Primaire', 'name_en' => 'Primary'],
        );

        return Classe::create(['school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'nom' => $nom]);
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    private function importer(array $lignes): CompetenceImport
    {
        $import = new CompetenceImport($this->school->id, app(CompetenceAttributionService::class));
        $import->collection(collect($lignes)->map(fn (array $ligne) => collect($ligne)));

        return $import;
    }

    /** Le cœur du découpage : deux lignes, une compétence, deux barèmes. */
    public function test_une_compétence_recoit_un_bareme_different_par_classe(): void
    {
        $cm2 = $this->classe('CM2');
        $cp = $this->classe('CP');

        $import = $this->importer([
            [
                'competence' => 'Langue et communication', 'classe' => 'CM2', 'notation' => 20,
                'oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5,
            ],
            [
                'competence' => 'Langue et communication', 'classe' => 'CP', 'notation' => 10,
                'oral' => 5, 'ecrit' => 3, 'savoir_etre' => 2,
            ],
        ]);

        // Une seule compétence créée, malgré les deux lignes : c'est la classe
        // qui change, pas ce que l'on évalue.
        $this->assertSame(1, $import->importedCount);
        $this->assertSame(2, $import->attributionsCount);
        $this->assertSame(1, Competence::count());

        $competence = Competence::sole();
        $attributionCm2 = ClasseCompetence::where('classe_id', $cm2->id)->sole();
        $attributionCp = ClasseCompetence::where('classe_id', $cp->id)->sole();

        $this->assertSame($competence->id, $attributionCm2->competence_id);
        $this->assertSame(20, $attributionCm2->bareme());
        $this->assertSame(10, $attributionCp->bareme());
        $this->assertSame(['oral' => 10.0, 'ecrit' => 5.0, 'savoir_etre' => 5.0], $attributionCm2->repartitionVolets());
        $this->assertSame(['oral' => 5.0, 'ecrit' => 3.0, 'savoir_etre' => 2.0], $attributionCp->repartitionVolets());
    }

    /** Le volet pratique se déclare en lui allouant des points, pas autrement. */
    public function test_le_volet_pratique_s_active_quand_la_ligne_lui_donne_des_points(): void
    {
        $this->classe('CM2');

        $this->importer([[
            'competence' => 'Motricité', 'classe' => 'CM2', 'notation' => 20,
            'oral' => 5, 'ecrit' => 5, 'savoir_etre' => 5, 'pratique' => 5,
        ]]);

        $attribution = ClasseCompetence::sole();

        $this->assertTrue($attribution->evalue_pratique);
        $this->assertSame(['oral', 'ecrit', 'savoir_etre', 'pratique'], $attribution->volets());
    }

    /** Attribuer une compétence installe ses matières : l'import ne court-circuite pas la règle. */
    public function test_l_import_installe_les_matieres_de_la_competence_dans_la_classe(): void
    {
        $classe = $this->classe('CM2');
        $competence = Competence::create([
            'school_id' => $this->school->id, 'label_fr' => 'Langue et communication',
        ]);
        Matiere::create(['school_id' => $this->school->id, 'competence_id' => $competence->id, 'nom' => 'Lecture']);
        Matiere::create(['school_id' => $this->school->id, 'competence_id' => $competence->id, 'nom' => 'Écriture']);

        $this->importer([[
            'competence' => 'Langue et communication', 'classe' => 'CM2', 'notation' => 20,
        ]]);

        $this->assertSame(2, ClasseMatiere::where('classe_id', $classe->id)->count());
    }

    /** Réimporter un fichier corrigé met le barème à jour plutôt que de le laisser tel quel. */
    public function test_un_reimport_corrige_le_bareme_de_l_attribution(): void
    {
        $this->classe('CM2');

        $this->importer([[
            'competence' => 'Langue et communication', 'classe' => 'CM2', 'notation' => 20,
            'oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5,
        ]]);

        $this->importer([[
            'competence' => 'Langue et communication', 'classe' => 'CM2', 'notation' => 30,
            'oral' => 10, 'ecrit' => 10, 'savoir_etre' => 10,
        ]]);

        $attribution = ClasseCompetence::sole();

        $this->assertSame(30, $attribution->bareme());
        $this->assertSame(['oral' => 10.0, 'ecrit' => 10.0, 'savoir_etre' => 10.0], $attribution->repartitionVolets());
    }

    /**
     * Une classe inconnue n'arrête pas l'import : la compétence entre au
     * référentiel et le libellé fautif remonte, pour que l'utilisateur corrige
     * son fichier plutôt que de comparer deux listes.
     */
    public function test_une_classe_introuvable_laisse_passer_la_competence(): void
    {
        $import = $this->importer([[
            'competence' => 'Langue et communication', 'classe' => 'CM9', 'notation' => 20,
        ]]);

        $this->assertSame(1, Competence::count());
        $this->assertSame(0, ClasseCompetence::count());
        $this->assertSame(['CM9'], $import->classesIntrouvables);
    }

    /** Sans colonne `Classe`, la ligne se contente de déclarer la compétence. */
    public function test_une_ligne_sans_classe_cree_la_competence_sans_attribution(): void
    {
        $import = $this->importer([[
            'competence' => 'Langue et communication', 'abreviation' => 'LC', 'ordre' => 2,
        ]]);

        $competence = Competence::sole();

        $this->assertSame('LC', $competence->abbreviation);
        $this->assertSame(2, $competence->ordre);
        $this->assertSame(0, $import->attributionsCount);
        $this->assertSame(0, ClasseCompetence::count());
    }

    /**
     * L'aller-retour : ce que l'export produit doit se relire tel quel, barème
     * par classe compris.
     */
    public function test_l_export_se_relit_par_l_import(): void
    {
        $this->classe('CM2');
        $this->classe('CP');

        $this->importer([
            [
                'competence' => 'Langue et communication', 'competence_en' => 'Language', 'abreviation' => 'LC',
                'ordre' => 1, 'classe' => 'CM2', 'notation' => 20, 'oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5,
            ],
            [
                'competence' => 'Langue et communication', 'classe' => 'CP', 'notation' => 10,
                'oral' => 5, 'ecrit' => 3, 'savoir_etre' => 2,
            ],
        ]);

        $export = new CompetenceExport($this->school->id);
        $lignes = $export->collection()->all();
        // Même normalisation que maatwebsite applique à la ligne d'en-tête :
        // « Competence (EN) » => `competence_en`, « Savoir-etre » => `savoir_etre`.
        $enTetes = array_map(
            fn (string $entete) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($entete)), '_'),
            $export->headings(),
        );

        // Deux lignes : une par classe, comme à l'entrée.
        $this->assertCount(2, $lignes);

        // Réimport du fichier produit, en repartant d'une base vierge de
        // compétences : si l'aller-retour tient, on retrouve exactement l'état
        // de départ.
        ClasseCompetence::query()->delete();
        Competence::query()->delete();

        $import = $this->importer(array_map(
            fn (array $ligne) => array_combine($enTetes, $ligne),
            $lignes,
        ));

        $this->assertSame(2, $import->attributionsCount);
        $this->assertSame(1, Competence::count());

        $competence = Competence::sole();
        $this->assertSame('Language', $competence->label_en);
        $this->assertSame('LC', $competence->abbreviation);

        $baremes = ClasseCompetence::with('classe')->get()
            ->mapWithKeys(fn (ClasseCompetence $cc) => [$cc->classe->nom => $cc->bareme()]);

        $this->assertSame(['CM2' => 20, 'CP' => 10], $baremes->all());
    }
}
