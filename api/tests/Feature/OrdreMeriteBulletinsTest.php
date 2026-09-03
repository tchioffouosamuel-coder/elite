<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\BulletinService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ordre des bulletins dans la liasse d'une classe.
 *
 * Un paquet de bulletins se distribue en conseil de classe, où l'on part du
 * premier : l'ordre alphabétique obligeait à retrier la pile à la main.
 */
class OrdreMeriteBulletinsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private Trimestre $trimestre;

    private Sequence $sequence;

    private ClasseMatiere $affectation;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $this->trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2025-09-01', 'date_fin' => '2025-12-15', 'is_active' => true,
        ]);

        $this->sequence = Sequence::create([
            'trimestre_id' => $this->trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1',
        ]);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Comptabilité']);

        $this->affectation = ClasseMatiere::create([
            'classe_id' => $this->classe->id,
            'matiere_id' => $matiere->id,
            'coefficient' => 1,
        ]);
    }

    /** Crée un élève et, si une note est fournie, la saisit pour la séquence. */
    private function eleve(string $nom, ?float $note = null): Eleve
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        if ($note !== null) {
            Note::create([
                'eleve_id' => $eleve->id,
                'classe_matiere_id' => $this->affectation->id,
                'sequence_id' => $this->sequence->id,
                'composante' => 'unique',
                'valeur' => $note,
            ]);
        }

        return $eleve;
    }

    /** @return list<string> */
    private function ordreDesBulletins(): array
    {
        $donnees = app(BulletinService::class)->donneesClasse($this->classe->fresh(), $this->trimestre);

        return array_map(fn (array $bulletin) => $bulletin['eleve']->nom_complet, $donnees['eleves']);
    }

    /** Le cas demandé : la liasse suit le classement, pas l'alphabet. */
    public function test_les_bulletins_sortent_par_ordre_de_merite(): void
    {
        // Noms choisis pour que l'alphabet contredise le mérite : ZAITOUNA,
        // premier de la classe, sortait dernier de la liasse.
        $this->eleve('ABDOURAMAN OUZAIFA', 11.0);
        $this->eleve('MOUHAMADOU AWALOU', 14.5);
        $this->eleve('ZAITOUNA ASHRAF', 18.0);

        $this->assertSame(
            ['ZAITOUNA ASHRAF', 'MOUHAMADOU AWALOU', 'ABDOURAMAN OUZAIFA'],
            $this->ordreDesBulletins(),
        );
    }

    /** Un élève sans aucune note n'a pas de rang : il ferme la liasse. */
    public function test_les_eleves_sans_note_ferment_la_liasse(): void
    {
        $this->eleve('AAA SANS NOTE');
        $this->eleve('MOUHAMADOU AWALOU', 14.5);
        $this->eleve('ZAITOUNA ASHRAF', 18.0);

        $this->assertSame(
            ['ZAITOUNA ASHRAF', 'MOUHAMADOU AWALOU', 'AAA SANS NOTE'],
            $this->ordreDesBulletins(),
        );
    }

    /** Deux élèves sans note se rangent entre eux par ordre alphabétique. */
    public function test_les_eleves_sans_rang_restent_alphabetiques(): void
    {
        $this->eleve('ZULU SANS NOTE');
        $this->eleve('ALPHA SANS NOTE');
        $this->eleve('MOUHAMADOU AWALOU', 14.5);

        $this->assertSame(
            ['MOUHAMADOU AWALOU', 'ALPHA SANS NOTE', 'ZULU SANS NOTE'],
            $this->ordreDesBulletins(),
        );
    }

    /** Le tri d'une sélection partielle suit la même règle. */
    public function test_une_selection_partielle_reste_ordonnee(): void
    {
        $premier = $this->eleve('ZAITOUNA ASHRAF', 18.0);
        $dernier = $this->eleve('ABDOURAMAN OUZAIFA', 11.0);
        $this->eleve('MOUHAMADOU AWALOU', 14.5);

        $donnees = app(BulletinService::class)
            ->donneesClasse($this->classe->fresh(), $this->trimestre, [$dernier->id, $premier->id]);

        $this->assertSame(
            ['ZAITOUNA ASHRAF', 'ABDOURAMAN OUZAIFA'],
            array_map(fn (array $b) => $b['eleve']->nom_complet, $donnees['eleves']),
        );
    }

    /**
     * Le bilan disciplinaire passe par une vue Blade dont la feuille de style
     * vient d'être réalignée sur celle des documents mPDF : ce test garde sa
     * compilation sous surveillance.
     */
    public function test_le_bilan_disciplinaire_se_genere_toujours(): void
    {
        $this->eleve('ZAITOUNA ASHRAF', 18.0);
        $this->eleve('ABDOURAMAN OUZAIFA', 11.0);

        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/classes/{$this->classe->id}/bilan-disciplinaire/pdf?trimestre_id={$this->trimestre->id}")
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    /** L'effectif affiché reste celui de la classe, pas celui de la sélection. */
    public function test_l_effectif_du_document_reste_celui_de_la_classe(): void
    {
        $premier = $this->eleve('ZAITOUNA ASHRAF', 18.0);
        $this->eleve('MOUHAMADOU AWALOU', 14.5);
        $this->eleve('ABDOURAMAN OUZAIFA', 11.0);

        $donnees = app(BulletinService::class)
            ->donneesClasse($this->classe->fresh(), $this->trimestre, [$premier->id]);

        $this->assertCount(1, $donnees['eleves']);
        $this->assertSame(3, $donnees['effectif']['total']);
    }
}
