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
 * Cadence journalière du primaire et de la maternelle : l'enseignant y tient
 * sa classe du matin au soir et la direction lui demande un nombre fixe de
 * déclarations par jour, pas une par créneau. Ce compte atteint, la journée
 * est réputée faite.
 */
class CadenceJournaliereTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    /** @var array<string, ClasseMatiere> */
    private array $affectations = [];

    /** @var array<string, ProgressionItem> */
    private array $leconsCourantes = [];

    private ProgressionItem $leconDuJour;

    private ProgressionItem $leconDemain;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EPR', 'type' => 'primaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $compte = User::create([
            'name' => 'Compte prof', 'email' => 'maitresse@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $compte->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'ABENA Claire', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $this->prof = $compte->fresh();
        $personnelId = $this->prof->personnel->id;

        $classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CP-A',
            'niveau_classe' => 'CP', 'titulaire_id' => $personnelId,
        ]);

        foreach (['Lecture' => '08:00', 'Calcul' => '10:00', 'Éveil' => '14:00'] as $nom => $heure) {
            $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => $nom, 'statut' => 'actif']);
            $cm = ClasseMatiere::create([
                'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
                'personnel_id' => $personnelId, 'statut' => 'actif',
            ]);
            foreach (range(1, 7) as $jour) {
                EmploiDuTemps::create([
                    'school_id' => $this->school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $cm->id,
                    'jour' => $jour, 'heure_debut' => $heure, 'heure_fin' => '23:59',
                ]);
            }
            $this->affectations[$nom] = $cm;

            // Toute déclaration doit cocher au moins une leçon : chaque
            // matière en reçoit une, sans date planifiée.
            $this->leconsCourantes[$nom] = ProgressionItem::create([
                'classe_matiere_id' => null, 'matiere_id' => $matiere->id, 'niveau_classe' => 'CP',
                'personnel_id' => $personnelId, 'type' => 'lecon',
                'titre' => "Séance du jour — {$nom}", 'ordre' => 10,
            ]);
        }

        // Leçons de la 3e affectation : l'une planifiée aujourd'hui, l'autre
        // demain. Seule la première doit être cochée d'office.
        $eveil = $this->affectations['Éveil'];
        $this->leconDuJour = ProgressionItem::create([
            'classe_matiere_id' => null, 'matiere_id' => $eveil->matiere_id, 'niveau_classe' => 'CP',
            'personnel_id' => $personnelId, 'type' => 'lecon', 'titre' => 'Les saisons', 'ordre' => 0,
            'date_prevue' => now()->toDateString(),
        ]);
        $this->leconDemain = ProgressionItem::create([
            'classe_matiere_id' => null, 'matiere_id' => $eveil->matiere_id, 'niveau_classe' => 'CP',
            'personnel_id' => $personnelId, 'type' => 'lecon', 'titre' => 'Les animaux', 'ordre' => 1,
            'date_prevue' => now()->addDay()->toDateString(),
        ]);
    }

    private function regle(int $validationsParJour): void
    {
        RegleValidationSeance::updateOrCreate(
            ['school_id' => $this->school->id, 'sous_systeme_id' => null],
            [
                'methode_validation' => 'libre', 'delai_valeur' => 15, 'delai_unite' => 'minutes',
                'validations_par_jour' => $validationsParJour, 'appels_par_jour' => 2,
            ],
        );
    }

    /** @param list<int>|null $lecons */
    private function declarer(string $matiere, ?array $lecons = null)
    {
        return $this->actingAs($this->prof, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->affectations[$matiere]->id}", [
                'lecons' => $lecons ?? [$this->leconsCourantes[$matiere]->id],
                'appel' => [],
            ]);
    }

    private function seance(string $matiere): ?Seance
    {
        return Seance::where('classe_matiere_id', $this->affectations[$matiere]->id)
            ->whereDate('date_seance', now()->toDateString())
            ->first();
    }

    public function test_la_journee_se_cloture_quand_le_nombre_de_validations_est_atteint(): void
    {
        $this->regle(2);

        $this->declarer('Lecture')->assertOk();
        $this->assertNull($this->seance('Éveil'), 'une seule validation ne doit rien clôturer');

        $this->declarer('Calcul')->assertOk();

        $eveil = $this->seance('Éveil');
        $this->assertNotNull($eveil);
        $this->assertSame('effectuee', $eveil->statut);
    }

    public function test_les_lecons_planifiees_du_jour_sont_cochees_avec_leur_date_de_realisation(): void
    {
        $this->regle(2);

        $this->declarer('Lecture')->assertOk();
        $this->declarer('Calcul')->assertOk();

        $eveil = $this->seance('Éveil');
        $cochees = $eveil->lecons()->pluck('progression_items.id');

        $this->assertTrue($cochees->contains($this->leconDuJour->id));
        $this->assertFalse($cochees->contains($this->leconDemain->id), 'une leçon planifiée demain ne doit pas être cochée');
        $this->assertSame(
            now()->toDateString(),
            $this->leconDuJour->fresh()->date_realisee?->format('Y-m-d'),
        );
    }

    /** L'appel reste à prendre : la journée est réputée faite côté leçons, pas côté pointage. */
    public function test_la_cloture_ne_verrouille_pas_l_appel_des_seances_restantes(): void
    {
        $this->regle(2);

        $this->declarer('Lecture')->assertOk();
        $this->declarer('Calcul')->assertOk();

        $eveil = $this->seance('Éveil');
        $this->assertNull($eveil->appel_verrouille_le);
        $this->assertFalse($eveil->appelVerrouille());
    }

    /**
     * Sans cadence définie — le cas du secondaire, et le défaut — chaque
     * cours se déclare séparément. C'est le garde-fou de la valeur 0 : à 1,
     * la première déclaration emporterait toute la journée.
     */
    public function test_sans_cadence_definie_rien_ne_se_cloture_tout_seul(): void
    {
        $this->regle(0);

        $this->declarer('Lecture')->assertOk();
        $this->declarer('Calcul')->assertOk();

        $this->assertNull($this->seance('Éveil'));
    }

    public function test_une_seance_deja_declaree_n_est_pas_retouchee_par_la_cloture(): void
    {
        $this->regle(2);

        // La maîtresse déclare elle-même l'Éveil, en cochant une leçon qui
        // n'était PAS planifiée ce jour-là : c'est son choix explicite.
        $this->declarer('Éveil', [$this->leconDemain->id])->assertOk();
        $this->declarer('Lecture')->assertOk();

        $cochees = $this->seance('Éveil')->lecons()->pluck('progression_items.id');

        // La clôture ne vient pas y ajouter la leçon du jour : ce que
        // l'enseignante a dit prime sur ce que la règle présume.
        $this->assertTrue($cochees->contains($this->leconDemain->id));
        $this->assertFalse($cochees->contains($this->leconDuJour->id));
    }
}
