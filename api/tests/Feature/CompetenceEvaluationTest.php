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
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\MoyennePrimaireService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Évaluation par compétence au primaire et en maternelle.
 *
 * La compétence est l'unité notée : elle porte le barème et les volets, la
 * matière n'est plus que le contenu qu'elle recouvre. Attribuer une compétence
 * à une classe y installe ses matières, et le bulletin affiche les compétences.
 */
class CompetenceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

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
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
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
            'school_id' => $this->school->id, 'nom' => 'CE1-A',
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function competence(array $attributs = []): Competence
    {
        return Competence::create([
            'school_id' => $this->school->id,
            'label_fr' => 'Langue et communication',
            'notation' => 20,
            'evalue_pratique' => false,
            'repartition_volets' => ['oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5],
            ...$attributs,
        ]);
    }

    private function matiere(Competence $competence, string $nom): Matiere
    {
        return Matiere::create([
            'school_id' => $this->school->id,
            'competence_id' => $competence->id,
            'nom' => $nom,
        ]);
    }

    private function agent(string $nom): Personnel
    {
        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $this->school->id, 'label_fr' => 'Enseignant'],
        );

        return Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => $nom,
            'fonction_id' => $fonction->id, 'statut' => 'actif',
        ]);
    }

    private function eleve(string $nom): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    // ------------------------------------------------------------ Structure

    /** La matière n'a plus de barème : il a suivi l'évaluation vers la compétence. */
    public function test_la_matiere_ne_porte_plus_la_notation(): void
    {
        $competence = $this->competence();
        $matiere = $this->matiere($competence, 'Lecture');

        $this->assertArrayNotHasKey('notation', $matiere->getAttributes());
        $this->assertArrayNotHasKey('evalue_pratique', $matiere->getAttributes());
        $this->assertArrayNotHasKey('repartition_volets', $matiere->getAttributes());
        $this->assertSame($competence->id, $matiere->competence_id);
    }

    public function test_le_volet_pratique_s_ajoute_quand_la_competence_l_evalue(): void
    {
        $sansPratique = $this->competence();
        $avecPratique = $this->competence([
            'label_fr' => 'Motricité',
            'evalue_pratique' => true,
            'repartition_volets' => ['oral' => 5, 'ecrit' => 5, 'savoir_etre' => 5, 'pratique' => 5],
        ]);

        $this->assertSame(['oral', 'ecrit', 'savoir_etre'], $sansPratique->volets());
        $this->assertSame(['oral', 'ecrit', 'savoir_etre', 'pratique'], $avecPratique->volets());
    }

    // ---------------------------------------------------------- Attribution

    /** Le cœur de la demande : attribuer un bloc installe tout son contenu. */
    public function test_attribuer_une_competence_installe_ses_matieres_dans_la_classe(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');
        $this->matiere($competence, 'Écriture');
        $this->matiere($competence, 'Langue nationale');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", [
                'competence_ids' => [$competence->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.attribuees', 1)
            ->assertJsonPath('data.matieres', 3);

        $this->assertSame(3, ClasseMatiere::where('classe_id', $this->classe->id)->count());
        $this->assertSame(1, ClasseCompetence::where('classe_id', $this->classe->id)->count());
    }

    /** Les matières installées héritent de l'enseignant du bloc. */
    public function test_les_matieres_installees_heritent_de_l_enseignant_de_la_competence(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');
        $enseignant = $this->agent('TITULAIRE PAUL');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", [
                'competence_ids' => [$competence->id],
                'personnel_id' => $enseignant->id,
            ])
            ->assertOk();

        $this->assertSame(
            $enseignant->id,
            ClasseMatiere::where('classe_id', $this->classe->id)->firstOrFail()->personnel_id,
        );
    }

    /** Sans enseignant explicite, le titulaire de la classe prend le bloc. */
    public function test_sans_enseignant_designe_le_titulaire_prend_la_competence(): void
    {
        $titulaire = $this->agent('TITULAIRE ANNE');
        $this->classe->update(['titulaire_id' => $titulaire->id]);

        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", [
                'competence_ids' => [$competence->id],
            ])
            ->assertOk();

        $this->assertSame(
            $titulaire->id,
            ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail()->personnel_id,
        );
    }

    /**
     * Une matière ajoutée après coup rejoint les classes qui portent déjà sa
     * compétence — sans quoi il faudrait repasser sur chacune.
     */
    public function test_une_matiere_ajoutee_ensuite_rejoint_les_classes_concernees(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]])
            ->assertOk();

        $this->assertSame(1, ClasseMatiere::where('classe_id', $this->classe->id)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/matieres', [
                'nom' => 'Écriture',
                'competence_id' => $competence->id,
                'school_id' => $this->school->id,
            ])
            ->assertCreated();

        $this->assertSame(2, ClasseMatiere::where('classe_id', $this->classe->id)->count());
    }

    /** Réattribuer ne duplique rien : l'opération est idempotente. */
    public function test_reattribuer_une_competence_ne_duplique_pas_les_matieres(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');

        foreach ([1, 2] as $ignore) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]])
                ->assertOk();
        }

        $this->assertSame(1, ClasseMatiere::where('classe_id', $this->classe->id)->count());
        $this->assertSame(1, ClasseCompetence::where('classe_id', $this->classe->id)->count());
    }

    /** Retirer le bloc retire les affectations qui en découlaient. */
    public function test_retirer_une_competence_retire_ses_matieres(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');
        $this->matiere($competence, 'Écriture');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $attribution = ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/classe-competences/{$attribution->id}")
            ->assertOk();

        $this->assertSame(0, ClasseMatiere::where('classe_id', $this->classe->id)->count());
        $this->assertSame(0, ClasseCompetence::where('classe_id', $this->classe->id)->count());
    }

    // ----------------------------------------------------------------- Notes

    public function test_la_grille_de_saisie_porte_sur_la_competence(): void
    {
        $competence = $this->competence();
        $this->matiere($competence, 'Lecture');
        $this->eleve('ELEVE UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $attribution = ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire?trimestre_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.bareme', 20)
            ->assertJsonPath('data.composantes', ['oral', 'ecrit', 'savoir_etre'])
            ->assertJsonCount(1, 'data.lignes');
    }

    /** Une note ne peut dépasser la part du barème allouée à son volet. */
    public function test_une_note_au_dela_du_volet_est_refusee(): void
    {
        $competence = $this->competence();
        $eleve = $this->eleve('ELEVE UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $attribution = ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();

        // Le volet « ecrit » est noté sur 5 : 8 doit être refusé.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'sequence_id' => $this->sequence->id,
                    'composante' => 'ecrit',
                    'valeur' => 8,
                ]],
            ])
            ->assertStatus(422);
    }

    /**
     * La note d'une compétence pour le trimestre est la moyenne des totaux de
     * séquence, chaque total étant la somme des volets.
     */
    public function test_la_note_de_la_competence_somme_ses_volets(): void
    {
        $competence = $this->competence();
        $eleve = $this->eleve('ELEVE UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $attribution = ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [
                    ['eleve_id' => $eleve->id, 'sequence_id' => $this->sequence->id, 'composante' => 'oral', 'valeur' => 8],
                    ['eleve_id' => $eleve->id, 'sequence_id' => $this->sequence->id, 'composante' => 'ecrit', 'valeur' => 4],
                    ['eleve_id' => $eleve->id, 'sequence_id' => $this->sequence->id, 'composante' => 'savoir_etre', 'valeur' => 3],
                ],
            ])
            ->assertOk();

        $resultat = app(MoyennePrimaireService::class)
            ->noteCompetenceEleve($eleve->fresh(), $attribution->fresh(), $this->trimestre);

        $this->assertSame(15.0, $resultat['note']);
        $this->assertSame(20, $resultat['bareme']);
    }

    // -------------------------------------------------------------- Bulletin

    /** Le bulletin liste les compétences, pas les matières qu'elles recouvrent. */
    public function test_le_bulletin_affiche_les_competences(): void
    {
        $competence = $this->competence(['label_fr' => 'Langue et communication']);
        $this->matiere($competence, 'Lecture');
        $this->matiere($competence, 'Écriture');
        $eleve = $this->eleve('ELEVE UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $donnees = app(\App\Services\BulletinPrimaireService::class)
            ->donneesClasse($this->classe->fresh(), $this->trimestre);

        $lignes = $donnees['eleves'][0]['lignes'];

        $this->assertCount(1, $lignes, 'Le bulletin doit porter une ligne par compétence, pas par matière.');
        $this->assertSame('Langue et communication', $lignes[0]['matiere']);
        $this->assertSame(20, $lignes[0]['bareme']);
        $this->assertSame($eleve->id, $donnees['eleves'][0]['eleve']->id);
    }

    /** Une compétence déjà notée ne se supprime pas : le bulletin y perdrait une ligne. */
    public function test_une_competence_notee_ne_se_supprime_pas(): void
    {
        $competence = $this->competence();
        $eleve = $this->eleve('ELEVE UN');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]]);

        $attribution = ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequence->id,
                    'composante' => 'oral', 'valeur' => 7,
                ]],
            ])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/competences/{$competence->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('competences', ['id' => $competence->id]);
    }

    /**
     * Chaque volet est facultatif : la somme n'a plus à égaler le barème pour
     * enregistrer — celui qu'on laisse vide compte simplement pour 0 point.
     */
    public function test_une_repartition_qui_ne_somme_pas_au_bareme_est_acceptee(): void
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->school->id,
                'label_fr' => 'Mathématiques',
                'notation' => 20,
                'repartition_volets' => ['oral' => 10, 'ecrit' => 10, 'savoir_etre' => 10],
            ])
            ->assertCreated();

        $competence = Competence::find($reponse->json('data.id'));
        $this->assertSame(['oral' => 10.0, 'ecrit' => 10.0, 'savoir_etre' => 10.0], $competence->repartitionVolets());
    }

    /** Un volet laissé de côté n'est même plus exigé dans le tableau. */
    public function test_un_volet_manquant_n_est_plus_refuse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->school->id,
                'label_fr' => 'Français',
                'notation' => 20,
                'repartition_volets' => ['oral' => 20],
            ])
            ->assertCreated();
    }

    /** Un volet non évalué (pratique décoché) ne doit toujours pas porter de points. */
    public function test_le_volet_pratique_non_evalue_ne_peut_pas_porter_de_points(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->school->id,
                'label_fr' => 'Sport',
                'notation' => 20,
                'evalue_pratique' => false,
                'repartition_volets' => ['pratique' => 5],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('repartition_volets.pratique');
    }
}
