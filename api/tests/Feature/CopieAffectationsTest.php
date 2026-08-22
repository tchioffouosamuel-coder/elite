<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
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
 * Copie d'affectations matière/enseignant d'une classe vers d'autres.
 *
 * L'enjeu tenu ici est l'enseignant. Au primaire et en maternelle, le titulaire
 * tient toutes les matières de sa classe : recopier celui de la classe source
 * désignerait un agent qui n'y enseigne pas. Au secondaire, à l'inverse, la
 * matière suit son professeur spécialiste.
 */
class CopieAffectationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password', 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /** @var array<int, int> school_id => annee_scolaire_id */
    private array $annees = [];

    private function ecole(string $type, string $code): School
    {
        $school = School::create(['name' => 'Elites '.$code, 'code' => $code, 'type' => $type, 'is_active' => true]);

        // `classes.annee_scolaire_id` est obligatoire : chaque école ouvre son année.
        $this->annees[$school->id] = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-07-31',
            'is_active' => true,
        ])->id;

        return $school;
    }

    private function agent(School $school, string $nom): Personnel
    {
        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $school->id, 'label_fr' => 'Enseignant'],
        );

        return Personnel::create([
            'school_id' => $school->id,
            'nom_complet' => $nom,
            'fonction_id' => $fonction->id,
            'statut' => 'actif',
        ]);
    }

    private function classe(School $school, string $nom, ?Personnel $titulaire = null): Classe
    {
        return Classe::create([
            'school_id' => $school->id,
            'annee_scolaire_id' => $this->annees[$school->id],
            'nom' => $nom,
            'titulaire_id' => $titulaire?->id,
        ]);
    }

    /** @param list<int> $cibles */
    private function copier(array $affectations, array $cibles): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-matieres/copier', [
                'affectation_ids' => $affectations,
                'classe_ids' => $cibles,
            ]);
    }

    /** Le cas demandé : la classe d'arrivée impose son propre titulaire. */
    public function test_au_primaire_la_copie_prend_le_titulaire_de_la_classe_cible(): void
    {
        $ecole = $this->ecole('primaire', 'EP');
        $matiere = Matiere::create(['school_id' => $ecole->id, 'nom' => 'Mathématiques']);

        $enseignantSource = $this->agent($ecole, 'SOURCE ANNE');
        $enseignantCible = $this->agent($ecole, 'CIBLE PAUL');

        $source = $this->classe($ecole, 'CE1-A', $enseignantSource);
        $cible = $this->classe($ecole, 'CE1-B', $enseignantCible);

        $affectation = ClasseMatiere::create([
            'classe_id' => $source->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $enseignantSource->id,
            'coefficient' => 4,
        ]);

        $this->copier([$affectation->id], [$cible->id])->assertOk();

        $copie = ClasseMatiere::where('classe_id', $cible->id)->firstOrFail();

        $this->assertSame($enseignantCible->id, $copie->personnel_id);
        $this->assertNotSame($enseignantSource->id, $copie->personnel_id);
        // Le reste de la configuration se copie bien, lui.
        $this->assertSame(4, (int) $copie->coefficient);
    }

    /** Sans titulaire, la colonne reste vide plutôt que fausse. */
    public function test_une_classe_cible_sans_titulaire_recoit_une_matiere_sans_enseignant(): void
    {
        $ecole = $this->ecole('primaire', 'EP');
        $matiere = Matiere::create(['school_id' => $ecole->id, 'nom' => 'Lecture']);
        $enseignantSource = $this->agent($ecole, 'SOURCE ANNE');

        $source = $this->classe($ecole, 'CE1-A', $enseignantSource);
        $cible = $this->classe($ecole, 'CE1-B');

        $affectation = ClasseMatiere::create([
            'classe_id' => $source->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $enseignantSource->id,
        ]);

        $this->copier([$affectation->id], [$cible->id])->assertOk();

        $this->assertNull(ClasseMatiere::where('classe_id', $cible->id)->firstOrFail()->personnel_id);
    }

    public function test_en_maternelle_la_regle_est_la_meme_qu_au_primaire(): void
    {
        $ecole = $this->ecole('maternelle', 'EM');
        $matiere = Matiere::create(['school_id' => $ecole->id, 'nom' => 'Éveil']);

        $enseignantSource = $this->agent($ecole, 'SOURCE ANNE');
        $enseignantCible = $this->agent($ecole, 'CIBLE PAUL');

        $source = $this->classe($ecole, 'Petite section A', $enseignantSource);
        $cible = $this->classe($ecole, 'Petite section B', $enseignantCible);

        $affectation = ClasseMatiere::create([
            'classe_id' => $source->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $enseignantSource->id,
        ]);

        $this->copier([$affectation->id], [$cible->id])->assertOk();

        $this->assertSame(
            $enseignantCible->id,
            ClasseMatiere::where('classe_id', $cible->id)->firstOrFail()->personnel_id,
        );
    }

    /** Au secondaire, la matière suit son professeur : comportement inchangé. */
    public function test_au_secondaire_l_enseignant_de_la_source_est_conserve(): void
    {
        $ecole = $this->ecole('secondaire', 'ES');
        $matiere = Matiere::create(['school_id' => $ecole->id, 'nom' => 'Physique']);

        $professeur = $this->agent($ecole, 'PROF PHYSIQUE');
        $titulaireCible = $this->agent($ecole, 'PROF PRINCIPAL');

        $source = $this->classe($ecole, '6e A');
        $cible = $this->classe($ecole, '6e B', $titulaireCible);

        $affectation = ClasseMatiere::create([
            'classe_id' => $source->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $professeur->id,
        ]);

        $this->copier([$affectation->id], [$cible->id])->assertOk();

        $copie = ClasseMatiere::where('classe_id', $cible->id)->firstOrFail();

        $this->assertSame($professeur->id, $copie->personnel_id);
        $this->assertNotSame($titulaireCible->id, $copie->personnel_id);
    }

    /**
     * Un complexe copie couramment d'une école vers une autre : chaque classe
     * d'arrivée applique la règle de SA propre école, pas celle de la source.
     */
    public function test_chaque_classe_cible_applique_la_regle_de_sa_propre_ecole(): void
    {
        $secondaire = $this->ecole('secondaire', 'ES');
        $primaire = $this->ecole('primaire', 'EP');

        $matiere = Matiere::create(['school_id' => $secondaire->id, 'nom' => 'Anglais']);
        $professeur = $this->agent($secondaire, 'PROF ANGLAIS');
        $titulairePrimaire = $this->agent($primaire, 'TITULAIRE CM2');

        $source = $this->classe($secondaire, '6e A');
        $cibleSecondaire = $this->classe($secondaire, '6e B');
        $ciblePrimaire = $this->classe($primaire, 'CM2-A', $titulairePrimaire);

        $affectation = ClasseMatiere::create([
            'classe_id' => $source->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $professeur->id,
        ]);

        $this->copier([$affectation->id], [$cibleSecondaire->id, $ciblePrimaire->id])->assertOk();

        $this->assertSame(
            $professeur->id,
            ClasseMatiere::where('classe_id', $cibleSecondaire->id)->firstOrFail()->personnel_id,
        );
        $this->assertSame(
            $titulairePrimaire->id,
            ClasseMatiere::where('classe_id', $ciblePrimaire->id)->firstOrFail()->personnel_id,
        );
    }
}
