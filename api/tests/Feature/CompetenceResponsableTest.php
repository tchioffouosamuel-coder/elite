<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
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
use App\Services\NotePrimaireService;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Responsable d'une compétence, copie et retrait en lot.
 *
 * Au primaire, le titulaire tient par défaut toutes les compétences de sa
 * classe. Une compétence peut toutefois être confiée à un enseignant qui n'est
 * pas titulaire — un intervenant d'anglais ou de sport — qui gagne alors le
 * droit d'en saisir les notes sans rien retirer au titulaire.
 */
class CompetenceResponsableTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private Personnel $titulaire;

    private Personnel $intervenant;

    private User $admin;

    private User $utilisateurIntervenant;

    private Trimestre $trimestre;

    private Sequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Primaire', 'code' => 'EPR', 'type' => 'primaire', 'is_active' => true,
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

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $this->titulaire = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'TITULAIRE CE1',
            'fonction_id' => $fonction->id, 'statut' => 'actif',
        ]);

        $this->utilisateurIntervenant = User::create([
            'name' => 'Intervenant Anglais', 'email' => 'intervenant@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->intervenant = Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->utilisateurIntervenant->id,
            'nom_complet' => 'INTERVENANT ANGLAIS', 'fonction_id' => $fonction->id, 'statut' => 'actif',
        ]);
        $this->utilisateurIntervenant = $this->utilisateurIntervenant->fresh();

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CE1-A', 'titulaire_id' => $this->titulaire->id,
        ]);
    }

    private function competence(string $label = 'Communiquer en anglais'): Competence
    {
        return Competence::create(['school_id' => $this->school->id, 'label_fr' => $label]);
    }

    private function attribuer(Competence $competence, ?Classe $classe = null): ClasseCompetence
    {
        $classe ??= $this->classe;

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$classe->id}/competences", [
                'competence_ids' => [$competence->id],
                'notation' => 40,
                'repartition_volets' => ['oral' => 20, 'ecrit' => 15, 'savoir_etre' => 5],
            ])
            ->assertOk();

        return ClasseCompetence::where('classe_id', $classe->id)
            ->where('competence_id', $competence->id)
            ->firstOrFail();
    }

    private function confier(ClasseCompetence $attribution, ?int $personnelId): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/classe-competences/{$attribution->id}", ['personnel_id' => $personnelId])
            ->assertOk();
    }

    // ------------------------------------------------------------ Enseignant

    /** Sans désignation, la compétence revient au titulaire — et l'écran le dit. */
    public function test_sans_designation_la_competence_revient_au_titulaire(): void
    {
        $this->attribuer($this->competence());

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/classes/{$this->classe->id}/competences")
            ->assertOk()
            ->assertJsonPath('data.0.personnel_id', null)
            ->assertJsonPath('data.0.enseignant.nom_complet', 'TITULAIRE CE1')
            ->assertJsonPath('data.0.enseignant.herite', true);
    }

    /** Confier la compétence nomme l'enseignant et redescend sur ses matières. */
    public function test_confier_une_competence_rebascule_ses_matieres_sur_l_enseignant(): void
    {
        $competence = $this->competence();
        Matiere::create([
            'school_id' => $this->school->id, 'competence_id' => $competence->id, 'nom' => 'Oral anglais',
        ]);
        $attribution = $this->attribuer($competence);

        $this->assertSame(
            $this->titulaire->id,
            ClasseMatiere::where('classe_id', $this->classe->id)->first()->personnel_id,
        );

        $this->confier($attribution, $this->intervenant->id);

        $this->assertSame($this->intervenant->id, $attribution->fresh()->personnel_id);
        $this->assertSame(
            $this->intervenant->id,
            ClasseMatiere::where('classe_id', $this->classe->id)->first()->personnel_id,
        );

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/classes/{$this->classe->id}/competences")
            ->assertJsonPath('data.0.enseignant.nom_complet', 'INTERVENANT ANGLAIS')
            ->assertJsonPath('data.0.enseignant.herite', false);
    }

    /** `null` rend la compétence au titulaire : c'est le seul moyen de défaire une délégation. */
    public function test_rendre_la_competence_au_titulaire(): void
    {
        $competence = $this->competence();
        Matiere::create([
            'school_id' => $this->school->id, 'competence_id' => $competence->id, 'nom' => 'Oral anglais',
        ]);
        $attribution = $this->attribuer($competence);

        $this->confier($attribution, $this->intervenant->id);
        $this->confier($attribution, null);

        $this->assertNull($attribution->fresh()->personnel_id);
        $this->assertSame(
            $this->titulaire->id,
            ClasseMatiere::where('classe_id', $this->classe->id)->first()->personnel_id,
        );
    }

    /** Le responsable nommé saisit la compétence ; le titulaire garde la sienne. */
    public function test_l_enseignant_designe_peut_saisir_sans_rien_retirer_au_titulaire(): void
    {
        $attribution = $this->attribuer($this->competence());
        $notes = app(NotePrimaireService::class);

        $utilisateurTitulaire = User::create([
            'name' => 'Titulaire', 'email' => 'titulaire@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->titulaire->update(['user_id' => $utilisateurTitulaire->id]);
        $utilisateurTitulaire = $utilisateurTitulaire->fresh();

        $this->assertFalse($notes->peutSaisir($this->utilisateurIntervenant, $attribution));

        $this->confier($attribution, $this->intervenant->id);
        $attribution->refresh();

        $this->assertTrue($notes->peutSaisir($this->utilisateurIntervenant, $attribution));
        $this->assertTrue($notes->peutSaisir($utilisateurTitulaire, $attribution));
    }

    /** Une compétence confiée ne transforme pas toute la classe en classe de l'intervenant. */
    public function test_confier_une_competence_n_ouvre_pas_toute_la_classe_a_l_intervenant(): void
    {
        $attribution = $this->attribuer($this->competence());
        $this->confier($attribution, $this->intervenant->id);

        $this->assertTrue(app(NotePrimaireService::class)->peutSaisir(
            $this->utilisateurIntervenant,
            $attribution->fresh(['classe']),
        ));
        $this->assertSame([], $this->utilisateurIntervenant->fresh()->perimetre()->classes());

        $this->actingAs($this->utilisateurIntervenant->fresh(), 'sanctum')
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->utilisateurIntervenant->fresh(), 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.scope', 'classe')
            ->assertJsonPath('data.effectifs.classes', 0)
            ->assertJsonPath('data.effectifs.eleves', 0);
    }

    /** Un agent d'une autre école ne peut pas se voir confier la compétence. */
    public function test_un_agent_d_une_autre_ecole_est_refuse(): void
    {
        $autreEcole = School::create([
            'name' => 'Autre', 'code' => 'AUT', 'type' => 'primaire', 'is_active' => true,
        ]);
        $etranger = Personnel::create([
            'school_id' => $autreEcole->id, 'nom_complet' => 'AGENT AILLEURS', 'statut' => 'actif',
        ]);
        $attribution = $this->attribuer($this->competence());

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/classe-competences/{$attribution->id}", ['personnel_id' => $etranger->id])
            ->assertStatus(422);

        $this->assertNull($attribution->fresh()->personnel_id);
    }

    // ------------------------------------------------------------------ Copie

    /** La copie reprend le barème, installe les matières, et laisse l'enseignant à la classe d'arrivée. */
    public function test_copier_des_competences_vers_une_autre_classe(): void
    {
        $autreTitulaire = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'TITULAIRE CE2', 'statut' => 'actif',
        ]);
        $cible = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CE2-A', 'titulaire_id' => $autreTitulaire->id,
        ]);

        $competence = $this->competence();
        Matiere::create([
            'school_id' => $this->school->id, 'competence_id' => $competence->id, 'nom' => 'Oral anglais',
        ]);
        $attribution = $this->attribuer($competence);
        $this->confier($attribution, $this->intervenant->id);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/copier', [
                'attribution_ids' => [$attribution->id],
                'classe_ids' => [$cible->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.copiees', 1)
            ->assertJsonPath('data.ignorees', 0)
            ->assertJsonPath('data.matieres', 1);

        $copie = ClasseCompetence::where('classe_id', $cible->id)->firstOrFail();

        $this->assertSame(40, $copie->notation);
        $this->assertSame(['oral' => 20.0, 'ecrit' => 15.0, 'savoir_etre' => 5.0], $copie->repartitionVolets());
        // L'enseignant ne se recopie pas : la classe d'arrivée confie ses
        // compétences à son propre titulaire.
        $this->assertNull($copie->personnel_id);
        $this->assertSame(
            $autreTitulaire->id,
            ClasseMatiere::where('classe_id', $cible->id)->first()->personnel_id,
        );
    }

    /** Une compétence déjà attribuée dans la classe visée est ignorée, jamais écrasée. */
    public function test_la_copie_n_ecrase_pas_une_competence_deja_attribuee(): void
    {
        $cible = Classe::create(['school_id' => $this->school->id, 'nom' => 'CE2-B']);
        $competence = $this->competence();
        $attribution = $this->attribuer($competence);

        $existante = $this->attribuer($competence, $cible);
        $existante->update(['notation' => 10]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/copier', [
                'attribution_ids' => [$attribution->id],
                'classe_ids' => [$cible->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.copiees', 0)
            ->assertJsonPath('data.ignorees', 1);

        $this->assertSame(10, $existante->fresh()->notation);
    }

    // ------------------------------------------------------------ Retrait en lot

    /** Le lot emporte les attributions et les matières qu'elles avaient installées. */
    public function test_retirer_plusieurs_competences_d_un_coup(): void
    {
        $premiere = $this->competence('Communiquer en anglais');
        Matiere::create([
            'school_id' => $this->school->id, 'competence_id' => $premiere->id, 'nom' => 'Oral anglais',
        ]);
        $seconde = $this->competence('Communiquer en français');

        $ids = [$this->attribuer($premiere)->id, $this->attribuer($seconde)->id];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/batch-delete', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.retirees', 2);

        $this->assertSame(0, ClasseCompetence::where('classe_id', $this->classe->id)->count());
        $this->assertSame(0, ClasseMatiere::where('classe_id', $this->classe->id)->count());
    }

    /** Une seule compétence notée dans le lot suffit à exiger le mot de passe. */
    public function test_le_lot_exige_le_mot_de_passe_des_qu_une_competence_porte_des_notes(): void
    {
        $notee = $this->attribuer($this->competence('Communiquer en anglais'));
        $vierge = $this->attribuer($this->competence('Communiquer en français'));

        $eleve = $this->inscrireAnneeActive(Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]));
        Note::create([
            'eleve_id' => $eleve->id, 'classe_competence_id' => $notee->id,
            'sequence_id' => $this->sequence->id, 'composante' => 'oral', 'valeur' => 12,
        ]);

        $ids = [$notee->id, $vierge->id];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/batch-delete', ['ids' => $ids])
            ->assertStatus(409);

        $this->assertSame(2, ClasseCompetence::where('classe_id', $this->classe->id)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/batch-delete', ['ids' => $ids, 'mot_de_passe' => 'mauvais'])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/classe-competences/batch-delete', ['ids' => $ids, 'mot_de_passe' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.retirees', 2);

        $this->assertSame(0, ClasseCompetence::where('classe_id', $this->classe->id)->count());
        $this->assertDatabaseMissing('notes', ['classe_competence_id' => $notee->id]);
    }
}
