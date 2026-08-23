<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\AppreciationService;
use App\Services\BulletinPrimaireService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Évaluation par appréciation à la maternelle.
 *
 * On n'y note pas sur vingt des enfants de trois ans : l'enseignante coche un
 * visage, et le bulletin colore la case du niveau atteint. Ni moyenne, ni rang.
 */
class AppreciationMaternelleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private Trimestre $trimestre;

    private Sequence $sequence1;

    private Sequence $sequence2;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Maternelle',
            'code' => 'EM',
            'type' => 'maternelle',
            'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-07-31',
            'is_active' => true,
        ]);

        $this->trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id,
            'libelle' => 'Trimestre 1',
            'ordre' => 1,
            'date_debut' => '2025-09-01',
            'date_fin' => '2025-12-15',
            'is_active' => true,
        ]);

        $this->sequence1 = Sequence::create([
            'trimestre_id' => $this->trimestre->id,
            'libelle' => 'Séquence 1',
            'ordre' => 1,
        ]);
        $this->sequence2 = Sequence::create([
            'trimestre_id' => $this->trimestre->id,
            'libelle' => 'Séquence 2',
            'ordre' => 2,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id,
            'nom' => 'Petite section A',
        ]);

        $this->admin = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function attribution(): ClasseCompetence
    {
        $competence = Competence::create([
            'school_id' => $this->school->id,
            'label_fr' => 'Communiquer en anglais',
            'label_en' => 'Communicate in English',
            'notation' => 20,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classes/{$this->classe->id}/competences", ['competence_ids' => [$competence->id]])
            ->assertOk();

        return ClasseCompetence::where('classe_id', $this->classe->id)->firstOrFail();
    }

    /**
     * Niveaux de l'école, dans l'ordre. Passe par le service, qui pose les
     * niveaux d'usage au premier accès — exactement ce que fait l'écran de
     * saisie avant d'afficher sa grille.
     */
    private function niveaux(): \Illuminate\Database\Eloquent\Collection
    {
        return app(AppreciationService::class)->referentiel($this->school->id);
    }

    private function eleve(string $nom = 'ENFANT UN'): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    // ----------------------------------------------------------- Référentiel

    /** « Paramétrable » ne veut pas dire « vide » : les niveaux d'usage sont posés. */
    public function test_le_referentiel_se_dote_de_ses_niveaux_a_la_premiere_lecture(): void
    {
        $this->assertSame(0, Appreciation::count());

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/appreciations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label_fr', 'Acquis')
            ->assertJsonPath('data.0.couleur', '#16a34a')
            ->assertJsonPath('data.2.couleur', '#dc2626');
    }

    public function test_l_ecole_ajoute_son_propre_niveau(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/appreciations', [
                'school_id' => $this->school->id,
                'label_fr' => 'Dépassement',
                'emoji' => '⭐',
                'couleur' => '#2563eb',
                'ordre' => 0,
            ])
            ->assertStatus(422); // `ordre` commence à 1.

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/appreciations', [
                'school_id' => $this->school->id,
                'label_fr' => 'Dépassement',
                'emoji' => '⭐',
                'couleur' => '#2563eb',
                'ordre' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.emoji', '⭐');
    }

    public function test_la_suppression_de_tous_les_codes_ne_les_recree_pas(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/appreciations')
            ->assertOk();

        $ids = Appreciation::where('school_id', $this->school->id)->pluck('id');

        foreach ($ids as $id) {
            $this->actingAs($this->admin, 'sanctum')
                ->deleteJson("/api/v1/appreciations/{$id}")
                ->assertOk();
        }

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/appreciations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_une_couleur_invalide_est_refusee(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/appreciations', [
                'school_id' => $this->school->id,
                'label_fr' => 'Test',
                'couleur' => 'vert',
                'ordre' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('couleur');
    }

    // ---------------------------------------------------------------- Saisie

    public function test_la_grille_de_maternelle_bascule_en_mode_appreciation(): void
    {
        $attribution = $this->attribution();
        $this->eleve();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire?trimestre_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.mode', 'appreciation')
            ->assertJsonCount(3, 'data.appreciations')
            ->assertJsonPath('data.appreciations.0.emoji', '🙂');
    }

    /** La note de maternelle porte un niveau, pas un nombre. */
    public function test_la_saisie_enregistre_l_appreciation_sans_valeur_chiffree(): void
    {
        $attribution = $this->attribution();
        $eleve = $this->eleve();
        $acquis = $this->niveaux()->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'sequence_id' => $this->sequence1->id,
                    'composante' => 'oral',
                    'appreciation_id' => $acquis->id,
                ]],
            ])
            ->assertOk();

        $note = Note::where('eleve_id', $eleve->id)->firstOrFail();

        $this->assertSame($acquis->id, $note->appreciation_id);
        $this->assertNull($note->valeur);
    }

    /** Le plafond par volet ne s'applique pas : il n'y a pas de barème ici. */
    public function test_la_saisie_de_maternelle_n_est_pas_bornee_par_un_bareme(): void
    {
        $attribution = $this->attribution();
        $eleve = $this->eleve();
        $niveau = $this->niveaux()->first();

        // Une valeur chiffrée absurde accompagne la requête : elle doit être
        // ignorée plutôt que de faire échouer la saisie du visage.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'sequence_id' => $this->sequence1->id,
                    'composante' => 'ecrit',
                    'valeur' => 999,
                    'appreciation_id' => $niveau->id,
                ]],
            ])
            ->assertOk();

        $this->assertNull(Note::where('eleve_id', $eleve->id)->firstOrFail()->valeur);
    }

    /** Un niveau d'une autre école n'a rien à faire dans ce référentiel. */
    public function test_une_appreciation_etrangere_est_ignoree(): void
    {
        $autre = School::create([
            'name' => 'Autre',
            'code' => 'AU',
            'type' => 'maternelle',
            'is_active' => true,
        ]);
        $niveauEtranger = Appreciation::create([
            'school_id' => $autre->id,
            'label_fr' => 'Acquis',
            'couleur' => '#16a34a',
            'ordre' => 1,
        ]);

        $attribution = $this->attribution();
        $eleve = $this->eleve();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'sequence_id' => $this->sequence1->id,
                    'composante' => 'oral',
                    'appreciation_id' => $niveauEtranger->id,
                ]],
            ])
            ->assertOk();

        $this->assertSame(0, Note::count());
    }

    // -------------------------------------------------------------- Bulletin

    /**
     * Le bulletin retient l'appréciation de la dernière séquence renseignée :
     * l'acquisition est une trajectoire, le livret dit où l'enfant en est.
     */
    public function test_le_bulletin_retient_la_derniere_sequence_renseignee(): void
    {
        $attribution = $this->attribution();
        $eleve = $this->eleve();

        $niveaux = $this->niveaux()->values();
        $acquis = $niveaux[0];
        $nonAcquis = $niveaux[2];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [
                    ['eleve_id' => $eleve->id, 'sequence_id' => $this->sequence1->id, 'composante' => 'oral', 'appreciation_id' => $nonAcquis->id],
                    ['eleve_id' => $eleve->id, 'sequence_id' => $this->sequence2->id, 'composante' => 'oral', 'appreciation_id' => $acquis->id],
                ],
            ])
            ->assertOk();

        $donnees = app(BulletinPrimaireService::class)->donneesClasse($this->classe->fresh(), $this->trimestre);

        $this->assertSame('appreciation', $donnees['mode']);

        $voletOral = collect($donnees['eleves'][0]['lignes'][0]['volets'])->firstWhere('code', 'oral');
        $this->assertSame($acquis->id, $voletOral['appreciation']['id']);
        $this->assertSame('#16a34a', $voletOral['appreciation']['couleur']);
    }

    /** Ni moyenne, ni rang, ni total : la maternelle ne classe pas ses élèves. */
    public function test_le_bulletin_de_maternelle_ne_porte_ni_moyenne_ni_rang(): void
    {
        $this->attribution();
        $this->eleve();

        $bulletin = app(BulletinPrimaireService::class)
            ->donneesClasse($this->classe->fresh(), $this->trimestre)['eleves'][0];

        $this->assertArrayNotHasKey('moyenne_generale', $bulletin);
        $this->assertArrayNotHasKey('rang', $bulletin);
        $this->assertArrayNotHasKey('total_obtenu', $bulletin);
    }

    /** Un volet non renseigné reste vide : aucune case ne se colore par défaut. */
    public function test_un_volet_non_renseigne_n_a_pas_d_appreciation(): void
    {
        $this->attribution();
        $this->eleve();

        $donnees = app(BulletinPrimaireService::class)->donneesClasse($this->classe->fresh(), $this->trimestre);

        foreach ($donnees['eleves'][0]['lignes'][0]['volets'] as $volet) {
            $this->assertNull($volet['appreciation']);
        }
    }

    public function test_le_bulletin_de_maternelle_s_edite_en_pdf(): void
    {
        $attribution = $this->attribution();
        $eleve = $this->eleve();
        $acquis = $this->niveaux()->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'sequence_id' => $this->sequence1->id,
                    'composante' => 'oral',
                    'appreciation_id' => $acquis->id,
                ]],
            ])
            ->assertOk();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/classes/{$this->classe->id}/bulletins-primaire?trimestre_id={$this->trimestre->id}")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    /** La maternelle n'a pas de barème à saisir sur sa compétence. */
    public function test_une_competence_de_maternelle_se_cree_sans_bareme(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/competences', [
                'school_id' => $this->school->id,
                'label_fr' => 'Vivre ensemble',
            ])
            ->assertCreated();
    }
}
