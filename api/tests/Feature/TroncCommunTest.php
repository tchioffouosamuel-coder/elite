<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use App\Models\User;
use App\Services\EmploiDuTempsService;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cours en tronc commun : plusieurs classes réunies devant un même enseignant.
 *
 * Le tableau de service du technique porte des lignes comme « ACT F3-ACC F3-
 * Marketing F3-Home eco F3 » : quatre classes, un cours, un appel. Ces tests
 * vérifient que le regroupement tient de bout en bout — grille, génération des
 * séances, feuille d'appel, et surtout enregistrement du pointage.
 */
class TroncCommunTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    /** @var array<string, Classe> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Tech', 'code' => 'ETC', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        foreach (['ACT F3', 'ACC F3', 'Marketing F3'] as $nom) {
            $this->classes[$nom] = Classe::create([
                'school_id' => $this->school->id,
                'nom' => $nom,
            ]);
        }
    }

    private function eleve(string $nom, string $classe, bool $inscrit = true): Eleve
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classes[$classe]->id,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        // Seuls les inscrits de l'année active sont appelés (Seance::elevesAttendus).
        if ($inscrit) {
            Preinscription::create([
                'school_id' => $this->school->id, 'eleve_id' => $eleve->id, 'annee_scolaire_id' => $this->annee->id,
                'type' => 'existant', 'statut' => 'validee', 'donnees_eleve' => [], 'donnees_tuteurs' => [],
            ]);
        }

        return $eleve;
    }

    /** @param list<string> $associees */
    private function creneau(string $porteuse, array $associees = [], int $jour = 1, string $debut = '08:00', string $fin = '10:00'): EmploiDuTemps
    {
        $classe = $this->classes[$porteuse];

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Citoyenneté '.uniqid()]);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 2,
        ]);

        $creneau = EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'classe_matiere_id' => $classeMatiere->id,
            'jour' => $jour,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
        ]);

        $creneau->classesAssociees()->sync(
            collect($associees)->map(fn (string $n) => $this->classes[$n]->id)->all(),
        );

        return $creneau->fresh(['classesAssociees']);
    }

    private function service(): EmploiDuTempsService
    {
        return app(EmploiDuTempsService::class);
    }

    // ------------------------------------------------------------- le modèle

    public function test_un_creneau_ordinaire_n_est_pas_un_tronc_commun(): void
    {
        $creneau = $this->creneau('ACT F3');

        $this->assertFalse($creneau->estTroncCommun());
        $this->assertSame(1, $creneau->toutesLesClasses()->count());
    }

    public function test_le_tronc_commun_se_deduit_des_classes_associees(): void
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3', 'Marketing F3']);

        $this->assertTrue($creneau->estTroncCommun());
        // Porteuse comprise : c'est le périmètre de l'appel.
        $this->assertSame(3, $creneau->toutesLesClasses()->count());
    }

    // ------------------------------------------------------------- la grille

    public function test_le_cours_apparait_dans_la_grille_de_chaque_classe(): void
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3', 'Marketing F3']);

        foreach (['ACT F3', 'ACC F3', 'Marketing F3'] as $nom) {
            $grille = $this->service()->grille($this->classes[$nom]);

            $this->assertCount(1, $grille, "grille de {$nom}");
            $this->assertSame($creneau->id, $grille->first()->id);
        }
    }

    public function test_une_classe_hors_du_groupe_ne_voit_pas_le_cours(): void
    {
        $this->creneau('ACT F3', ['ACC F3']);

        $this->assertCount(0, $this->service()->grille($this->classes['Marketing F3']));
    }

    // ------------------------------------------------------------- conflits

    public function test_un_creneau_qui_empiete_sur_une_classe_associee_est_refuse(): void
    {
        $this->creneau('ACT F3', ['ACC F3'], 1, '08:00', '10:00');

        // Marketing est libre, mais ACC est déjà pris à cette heure-là.
        $this->assertTrue($this->service()->chevauche(
            $this->classes['Marketing F3'], 1, '09:00', '11:00', null, [$this->classes['ACC F3']->id],
        ));
    }

    public function test_une_classe_deja_engagee_en_tronc_commun_ne_peut_pas_etre_reprise(): void
    {
        $this->creneau('ACT F3', ['ACC F3'], 1, '08:00', '10:00');

        // ACC est associée ailleurs : lui poser son propre cours au même
        // moment la convoquerait à deux endroits.
        $this->assertTrue($this->service()->chevauche($this->classes['ACC F3'], 1, '09:00', '11:00'));
    }

    public function test_un_creneau_sur_un_autre_horaire_passe(): void
    {
        $this->creneau('ACT F3', ['ACC F3'], 1, '08:00', '10:00');

        $this->assertFalse($this->service()->chevauche($this->classes['ACC F3'], 1, '10:00', '12:00'));
    }

    // -------------------------------------------------- génération de séances

    public function test_un_tronc_commun_ne_produit_qu_une_seule_seance(): void
    {
        $this->creneau('ACT F3', ['ACC F3', 'Marketing F3']);

        $lundi = Carbon::parse('2026-09-07');   // un lundi

        foreach ($this->classes as $classe) {
            $this->service()->genererSeances($classe, $lundi, $lundi, null);
        }

        // Un cours réel, un appel : générer depuis les trois classes ne doit
        // pas créer trois séances pour le même créneau.
        $this->assertSame(1, Seance::count());
    }

    // ---------------------------------------------------------- feuille d'appel

    private function seanceAvecEleves(): Seance
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3', 'Marketing F3']);

        $this->eleve('ATANGANA PAUL', 'ACT F3');
        $this->eleve('BEKONO MARIE', 'ACC F3');
        $this->eleve('CHOUAIBOU ALI', 'Marketing F3');

        $lundi = Carbon::parse('2026-09-07');
        $this->service()->genererSeances($this->classes['ACT F3'], $lundi, $lundi, null);

        return Seance::where('emploi_du_temps_id', $creneau->id)->firstOrFail();
    }

    public function test_la_feuille_d_appel_reunit_les_eleves_des_trois_classes(): void
    {
        $feuille = $this->service()->feuilleAppel($this->seanceAvecEleves());

        $this->assertCount(3, $feuille);
        $this->assertSame(
            ['ATANGANA PAUL', 'BEKONO MARIE', 'CHOUAIBOU ALI'],
            $feuille->pluck('eleve.nom_complet')->all(),
        );
    }

    public function test_chaque_ligne_de_l_appel_porte_la_classe_de_l_eleve(): void
    {
        $feuille = $this->service()->feuilleAppel($this->seanceAvecEleves());

        // Sans elle, l'enseignant ne saurait plus qui il pointe.
        $this->assertSame(
            ['ACT F3', 'ACC F3', 'Marketing F3'],
            $feuille->pluck('classe.nom')->all(),
        );
    }

    public function test_un_eleve_inactif_ne_figure_pas_a_l_appel(): void
    {
        $seance = $this->seanceAvecEleves();
        Eleve::where('nom_complet', 'BEKONO MARIE')->update(['statut' => 'inactif']);

        $this->assertCount(2, $this->service()->feuilleAppel($seance));
    }

    /** Une fiche restée dans la classe sans inscription pour l'année active n'est pas appelée. */
    public function test_un_eleve_non_inscrit_ne_figure_pas_a_l_appel(): void
    {
        $seance = $this->seanceAvecEleves();
        $this->eleve('NON INSCRIT', 'ACT F3', inscrit: false);

        $feuille = $this->service()->feuilleAppel($seance);

        $this->assertCount(3, $feuille);
        $this->assertNotContains('NON INSCRIT', $feuille->pluck('eleve.nom_complet')->all());
    }

    // ------------------------------------------- enregistrement du pointage

    /**
     * Le piège du tronc commun : la garde d'origine ne retenait que les élèves
     * de la classe porteuse. L'appel les affichait tous puis n'en enregistrait
     * qu'un tiers, sans la moindre erreur.
     */
    public function test_le_pointage_est_enregistre_pour_les_trois_classes(): void
    {
        $seance = $this->seanceAvecEleves();

        $lignes = Eleve::orderBy('nom_complet')->get()
            ->map(fn (Eleve $e) => ['eleve_id' => $e->id, 'statut' => 'absent', 'motif' => 'maladie'])
            ->all();

        $enregistres = $this->service()->enregistrerAppel($seance, $lignes);

        $this->assertSame(3, $enregistres);
        $this->assertSame(3, Presence::where('seance_id', $seance->id)->count());
    }

    public function test_un_eleve_hors_du_groupe_est_ecarte_du_pointage(): void
    {
        $seance = $this->seanceAvecEleves();

        $autre = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'Home eco F3',
        ]);
        $intrus = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $autre->id,
            'nom_complet' => 'INTRUS', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $enregistres = $this->service()->enregistrerAppel($seance, [
            ['eleve_id' => $intrus->id, 'statut' => 'absent'],
        ]);

        $this->assertSame(0, $enregistres);
        $this->assertSame(0, Presence::where('seance_id', $seance->id)->count());
    }

    // ------------------------------------------------------ liste des séances

    private function permissions(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $this->permissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /** Enseignant affecté à la seule classe donnée : un compte borné à son périmètre. */
    private function enseignantDe(string $classe): User
    {
        $this->permissions();
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

        $user = User::create([
            'name' => 'Prof', 'email' => 'prof.tronc@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        ClasseMatiere::create([
            'classe_id' => $this->classes[$classe]->id,
            'matiere_id' => Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais'])->id,
            'personnel_id' => $personnel->id, 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    public function test_la_seance_apparait_dans_la_liste_de_chaque_classe_associee(): void
    {
        $seance = $this->seanceAvecEleves();
        $admin = $this->admin();

        foreach (['ACT F3', 'ACC F3', 'Marketing F3'] as $nom) {
            $this->actingAs($admin, 'sanctum')
                ->withHeader('X-School-Id', $this->school->id)
                ->getJson("/api/v1/classes/{$this->classes[$nom]->id}/seances?date_debut=2026-09-07&date_fin=2026-09-07")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $seance->id);
        }
    }

    // ------------------------------------------------ génération et synchro

    public function test_generer_depuis_une_classe_associee_cree_la_seance_de_la_porteuse(): void
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3']);
        $lundi = Carbon::parse('2026-09-07');

        $this->service()->genererSeances($this->classes['ACC F3'], $lundi, $lundi, null);
        $this->service()->genererSeances($this->classes['ACT F3'], $lundi, $lundi, null);

        // Une seule séance, rattachée à la porteuse : générer d'abord depuis
        // la classe associée ne doit ni l'oublier ni la dédoubler.
        $seance = Seance::sole();
        $this->assertSame($creneau->id, $seance->emploi_du_temps_id);
        $this->assertSame($this->classes['ACT F3']->id, $seance->classe_id);
    }

    public function test_la_synchro_des_creneaux_porte_les_classes_associees(): void
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3', 'Marketing F3']);

        $ligne = collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/v1/sync?entites=emplois_du_temps')
                ->assertOk()
                ->json('data.donnees.emplois_du_temps')
        )->firstWhere('id', $creneau->id);

        $this->assertEqualsCanonicalizing(
            [$this->classes['ACC F3']->id, $this->classes['Marketing F3']->id],
            $ligne['classes_associees'],
        );
    }

    public function test_changer_les_classes_associees_fait_redescendre_le_creneau(): void
    {
        $creneau = $this->creneau('ACT F3', ['ACC F3']);
        $avant = $creneau->updated_at;

        Carbon::setTestNow(now()->addMinute());
        $creneau->synchroniserClassesAssociees([$this->classes['ACC F3']->id]);
        $this->assertEquals($avant, $creneau->fresh()->updated_at, 'Liste inchangée : pas de nouvelle synchro.');

        $creneau->synchroniserClassesAssociees([$this->classes['ACC F3']->id, $this->classes['Marketing F3']->id]);
        $this->assertTrue($creneau->fresh()->updated_at->gt($avant));
        Carbon::setTestNow();
    }

    public function test_un_enseignant_d_une_classe_associee_recoit_la_seance_en_synchro(): void
    {
        $seance = $this->seanceAvecEleves();

        $donnees = $this->actingAs($this->enseignantDe('ACC F3'), 'sanctum')
            ->getJson('/api/v1/sync?entites=seances,classe_matieres')
            ->assertOk()
            ->json('data.donnees');

        $this->assertContains($seance->id, collect($donnees['seances'])->pluck('id'));
        // L'affectation de la porteuse suit : c'est elle qui nomme la matière.
        $this->assertContains($seance->classe_matiere_id, collect($donnees['classe_matieres'])->pluck('id'));
    }

    public function test_une_seance_sans_creneau_reste_sur_sa_seule_classe(): void
    {
        $this->eleve('ATANGANA PAUL', 'ACT F3');
        $this->eleve('BEKONO MARIE', 'ACC F3');

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Rattrapage']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classes['ACT F3']->id, 'matiere_id' => $matiere->id, 'coefficient' => 1,
        ]);

        // Séance créée à la main, sans emploi du temps d'origine.
        $seance = Seance::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classes['ACT F3']->id,
            'classe_matiere_id' => $classeMatiere->id,
            'date_seance' => '2026-09-07',
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'statut' => 'prevue',
        ]);

        $feuille = $this->service()->feuilleAppel($seance);

        $this->assertCount(1, $feuille);
        $this->assertSame('ATANGANA PAUL', $feuille->first()['eleve']->nom_complet);
    }
}
