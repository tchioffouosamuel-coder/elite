<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\ProgressionItem;
use App\Models\RegleValidationSeance;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Même enseignant, même jour, même heure, même matière, plusieurs classes :
 * c'est un seul cours. Le déclarer dans une classe le déclare dans les
 * autres — mais l'appel, lui, reste propre à chaque classe.
 */
class CoursParallelesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    private ClasseMatiere $cmA;

    private ClasseMatiere $cmB;

    private ClasseMatiere $cmAutreMatiere;

    private ProgressionItem $lecon;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ETP', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        // Aucune preuve de présence exigée : ce test porte sur la
        // propagation, pas sur le QR.
        RegleValidationSeance::create([
            'school_id' => $this->school->id, 'methode_validation' => 'libre',
            'delai_valeur' => 15, 'delai_unite' => 'minutes',
        ]);

        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $compte = User::create([
            'name' => 'Compte prof', 'email' => 'prof@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $compte->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'MBARGA Paul', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->prof = $compte->fresh();
        $personnelId = $this->prof->personnel->id;

        $maths = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $histoire = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Histoire', 'statut' => 'actif']);

        // Même niveau pour les deux classes : la fiche de progression est
        // alors partagée (cf. ResolveurFicheProgression), donc les mêmes
        // leçons se retrouvent de part et d'autre.
        $a = Classe::create(['school_id' => $this->school->id, 'nom' => '5e A', 'niveau_classe' => '5ème']);
        $b = Classe::create(['school_id' => $this->school->id, 'nom' => '5e B', 'niveau_classe' => '5ème']);

        $this->cmA = $this->affectation($a->id, $maths->id, $personnelId, '08:00');
        $this->cmB = $this->affectation($b->id, $maths->id, $personnelId, '08:00');
        // Même heure, même enseignant, mais une autre matière : rien à voir
        // avec le cours de maths, elle ne doit pas être emportée.
        $this->cmAutreMatiere = $this->affectation($a->id, $histoire->id, $personnelId, '08:00');

        $this->lecon = ProgressionItem::create([
            'classe_matiere_id' => null, 'matiere_id' => $maths->id, 'niveau_classe' => '5ème',
            'personnel_id' => $personnelId, 'type' => 'lecon', 'titre' => 'Théorème de Thalès', 'ordre' => 0,
        ]);
    }

    private function affectation(int $classeId, int $matiereId, int $personnelId, string $heure): ClasseMatiere
    {
        $cm = ClasseMatiere::create([
            'classe_id' => $classeId, 'matiere_id' => $matiereId,
            'personnel_id' => $personnelId, 'statut' => 'actif',
        ]);

        // Un créneau chaque jour : le test tourne quel que soit le jour.
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'school_id' => $this->school->id, 'classe_id' => $classeId, 'classe_matiere_id' => $cm->id,
                'jour' => $jour, 'heure_debut' => $heure, 'heure_fin' => '09:00',
            ]);
        }

        return $cm;
    }

    private function declarer(ClasseMatiere $cm, array $corps = [])
    {
        return $this->actingAs($this->prof, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$cm->id}", [
                'lecons' => [$this->lecon->id],
                'appel' => [],
                'contenu' => 'Introduction au théorème',
                ...$corps,
            ]);
    }

    private function seanceDuJour(ClasseMatiere $cm): ?Seance
    {
        return Seance::where('classe_matiere_id', $cm->id)->whereDate('date_seance', now()->toDateString())->first();
    }

    public function test_declarer_le_cours_dans_une_classe_le_declare_dans_la_classe_parallele(): void
    {
        $this->declarer($this->cmA)->assertOk();

        $jumelle = $this->seanceDuJour($this->cmB);

        $this->assertNotNull($jumelle, 'la classe parallèle doit avoir reçu sa séance');
        $this->assertSame('effectuee', $jumelle->statut);
        $this->assertSame('Introduction au théorème', $jumelle->contenu);
        $this->assertTrue($jumelle->lecons()->where('progression_items.id', $this->lecon->id)->exists());
    }

    /** L'appel ne se propage pas : chaque classe a ses propres élèves. */
    public function test_la_classe_parallele_garde_son_appel_a_prendre(): void
    {
        $this->declarer($this->cmA)->assertOk();

        $jumelle = $this->seanceDuJour($this->cmB);

        $this->assertSame(0, $jumelle->presences()->count());
        // Sans ce `null`, la fenêtre de correction aurait démarré sur un
        // appel jamais pris, et se serait refermée avant que l'enseignant
        // ait eu l'occasion de pointer cette classe.
        $this->assertNull($jumelle->appel_verrouille_le);
        $this->assertFalse($jumelle->appelVerrouille());
    }

    public function test_une_autre_matiere_a_la_meme_heure_n_est_pas_emportee(): void
    {
        $this->declarer($this->cmA)->assertOk();

        $this->assertNull($this->seanceDuJour($this->cmAutreMatiere));
    }

    public function test_une_classe_d_un_autre_enseignant_n_est_pas_emportee(): void
    {
        $autre = User::create([
            'name' => 'Autre prof', 'email' => 'autre@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $autre->id,
            'fonction_id' => FonctionReferentiel::where('school_id', $this->school->id)->value('id'),
            'nom_complet' => 'NANA Rose', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '5e C', 'niveau_classe' => '5ème']);
        $cmC = $this->affectation($classe->id, $this->cmA->matiere_id, $personnel->id, '08:00');

        $this->declarer($this->cmA)->assertOk();

        $this->assertNull($this->seanceDuJour($cmC));
    }

    public function test_une_classe_a_une_autre_heure_n_est_pas_emportee(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '5e D', 'niveau_classe' => '5ème']);
        $tardive = $this->affectation($classe->id, $this->cmA->matiere_id, $this->prof->personnel->id, '14:00');

        $this->declarer($this->cmA)->assertOk();

        $this->assertNull($this->seanceDuJour($tardive));
    }

    /** Déclarer deux fois ne doit pas empiler les leçons sur la jumelle. */
    public function test_la_propagation_est_idempotente(): void
    {
        $this->declarer($this->cmA)->assertOk();
        $this->declarer($this->cmA)->assertOk();

        $this->assertSame(1, $this->seanceDuJour($this->cmB)->lecons()->count());
    }
}
