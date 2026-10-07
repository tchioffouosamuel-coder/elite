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
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un enseignant ne saisit que dans le trimestre actif ; la direction, elle,
 * peut encore corriger un trimestre déjà clos (rattrapage, erreur de saisie
 * découverte après coup) — cf. `User::peutSaisirHorsTrimestreActif()`.
 */
class NotesTrimestreActifTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Trimestre $trimestreActif;

    private Trimestre $trimestreClos;

    private Sequence $sequenceActive;

    private Sequence $sequenceClose;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->trimestreClos = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => false,
        ]);
        $this->trimestreActif = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 2', 'ordre' => 2,
            'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27', 'is_active' => true,
        ]);

        $this->sequenceClose = Sequence::create([
            'trimestre_id' => $this->trimestreClos->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);
        $this->sequenceActive = Sequence::create([
            'trimestre_id' => $this->trimestreActif->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function enseignant(string $nom, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $user = User::create([
            'name' => $nom, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    public function test_la_direction_ferme_et_rouvre_une_seule_sequence(): void
    {
        $url = "/api/v1/sequences/{$this->sequenceActive->id}/saisie";
        $this->actingAs($this->admin, 'sanctum')->patchJson($url, ['saisie_ouverte' => false])
            ->assertOk()->assertJsonPath('data.saisie_ouverte', false);
        $this->assertTrue($this->sequenceClose->fresh()->saisie_ouverte);
        $this->getJson('/api/v1/trimestres')->assertOk()
            ->assertJsonFragment(['saisie_ouverte' => false]);
        $this->getJson('/api/v1/sync?entites=sequences')->assertOk()
            ->assertJsonFragment(['saisie_ouverte' => false]);
        $this->patchJson($url, ['saisie_ouverte' => true])
            ->assertOk()->assertJsonPath('data.saisie_ouverte', true);
        $this->patchJson($url, ['saisie_ouverte' => 'invalide'])->assertUnprocessable();
        $this->assertTrue($this->sequenceActive->fresh()->saisie_ouverte);
    }

    public function test_un_enseignant_meme_privilegie_ne_peut_pas_rouvrir_la_saisie(): void
    {
        $prof = $this->enseignant('Prof privilegie', 'prof.privilegie@test.local');
        $prof->givePermissionTo('trimestres.update');
        $this->sequenceActive->update(['saisie_ouverte' => false]);
        $this->actingAs($prof, 'sanctum')
            ->patchJson("/api/v1/sequences/{$this->sequenceActive->id}/saisie", ['saisie_ouverte' => true])
            ->assertForbidden();
        $this->assertFalse($this->sequenceActive->fresh()->saisie_ouverte);
    }

    public function test_la_fermeture_est_limitee_au_perimetre_etablissement(): void
    {
        $autreEcole = School::create(['name' => 'Autre', 'code' => 'AUT', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $autreEcole->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Sequence 1', 'ordre' => 1]);
        $gestionnaire = User::create([
            'name' => 'Gestionnaire', 'email' => 'gestionnaire@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $gestionnaire->givePermissionTo('trimestres.update');
        $this->actingAs($gestionnaire, 'sanctum')
            ->patchJson("/api/v1/sequences/{$sequence->id}/saisie", ['saisie_ouverte' => false])
            ->assertNotFound();
        $this->assertTrue($sequence->fresh()->saisie_ouverte);
    }

    public function test_les_appreciations_maternelles_sont_bloquees_par_la_fermeture(): void
    {
        $this->school->update(['type' => 'maternelle']);
        $prof = $this->enseignant('Titulaire maternelle', 'titulaire.maternelle@test.local');
        $attribution = $this->classeCompetencePrimaire($prof);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE MATERNELLE', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->sequenceActive->update(['saisie_ouverte' => false]);
        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [['eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceActive->id,
                    'composante' => 'oral', 'appreciation_id' => null]],
            ])->assertForbidden();
        $this->assertDatabaseCount('notes', 0);
        $this->getJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire")
            ->assertOk()->assertJsonPath('data.sequences.0.saisie_ouverte', false);
    }

    private function classeMatiereAvecEleve(User $prof): array
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e fermeture']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE FERMETURE', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Calcul', 'statut' => 'actif']);
        $attribution = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        return [$attribution, $eleve];
    }

    public function test_fermeture_bloque_modification_et_effacement_puis_reouverture_autorise(): void
    {
        $prof = $this->enseignant('Prof fermeture', 'prof.fermeture@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        $url = "/api/v1/classe-matieres/{$attribution->id}/notes";
        $corps = ['sequence_id' => $this->sequenceActive->id,
            'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]]];
        $this->actingAs($prof, 'sanctum')->postJson($url, $corps)->assertOk();
        $this->sequenceActive->update(['saisie_ouverte' => false]);
        foreach ([7, null] as $valeur) {
            $corps['notes'][0]['valeur'] = $valeur;
            $this->postJson($url, $corps)->assertForbidden();
            $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 15]);
        }
        $this->sequenceActive->update(['saisie_ouverte' => true]);
        $corps['notes'][0]['valeur'] = 7;
        $this->postJson($url, $corps)->assertOk();
        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 7]);
    }

    public function test_l_observation_de_la_grille_secondaire_est_enregistree_et_renvoyee_au_bulletin(): void
    {
        $prof = $this->enseignant('Prof observation', 'prof.observation@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        Preinscription::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->trimestreActif->annee_scolaire_id,
            'eleve_id' => $eleve->id,
            'type' => 'existant',
            'statut' => 'validee',
            'donnees_eleve' => [],
            'donnees_tuteurs' => [],
        ]);
        $url = "/api/v1/classe-matieres/{$attribution->id}/notes";
        $texte = 'Participe activement en classe.';

        $this->actingAs($this->admin, 'sanctum')
            ->postJson($url, [
                'sequence_id' => $this->sequenceActive->id,
                'notes' => [[
                    'eleve_id' => $eleve->id,
                    'valeur' => 15,
                    'observation' => $texte,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('observations_evaluations', [
            'eleve_id' => $eleve->id,
            'trimestre_id' => $this->trimestreActif->id,
            'classe_matiere_id' => $attribution->id,
            'texte' => $texte,
        ]);

        $this->getJson("{$url}?sequence_id={$this->sequenceActive->id}")
            ->assertOk()
            ->assertJsonPath('data.0.observation', $texte);

        $bulletin = app(\App\Services\BulletinService::class)
            ->donneesClasse($attribution->classe, $this->trimestreActif);
        $ligne = collect($bulletin['eleves'][0]['groupes'])->flatten(1)->first();

        $this->assertSame($texte, $ligne['observation']);
    }

    public function test_import_et_synchronisation_ne_contournent_pas_une_sequence_fermee(): void
    {
        $prof = $this->enseignant('Prof import', 'prof.import@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        $this->sequenceActive->update(['saisie_ouverte' => false]);
        $this->actingAs($prof, 'sanctum')
            ->post("/api/v1/classe-matieres/{$attribution->id}/notes/import", [
                'sequence_id' => $this->sequenceActive->id,
                'file' => UploadedFile::fake()->createWithContent('notes.csv', "eleve_id,valeur\n{$eleve->id},15\n"),
            ], ['Accept' => 'application/json'])->assertForbidden();
        $this->postJson('/api/v1/sync', ['operations' => [[
            'id' => 'notes-fermees', 'methode' => 'POST',
            'chemin' => "classe-matieres/{$attribution->id}/notes",
            'corps' => ['sequence_id' => $this->sequenceActive->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]]],
        ]]])->assertOk()->assertJsonPath('data.resultats.0.statut', 403);
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_le_lot_primaire_mixte_est_refuse_sans_ecriture_partielle(): void
    {
        $prof = $this->enseignant('Titulaire fermeture', 'titulaire.fermeture@test.local');
        $attribution = $this->classeCompetencePrimaire($prof);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE PRIMAIRE', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $fermee = Sequence::create([
            'trimestre_id' => $this->trimestreActif->id, 'libelle' => 'Sequence 2',
            'ordre' => 2, 'saisie_ouverte' => false,
        ]);
        $notes = array_map(fn ($id) => [
            'eleve_id' => $eleve->id, 'sequence_id' => $id, 'composante' => 'oral', 'valeur' => 8,
        ], [$this->sequenceActive->id, $fermee->id]);
        $url = "/api/v1/classe-competences/{$attribution->id}/notes-primaire";
        $this->actingAs($prof, 'sanctum')->postJson($url, ['notes' => $notes])->assertForbidden();
        $this->assertDatabaseCount('notes', 0);
        $this->postJson($url, ['notes' => [$notes[0]]])->assertOk();
        $this->assertDatabaseCount('notes', 1);
    }

    public function test_la_direction_peut_corriger_une_sequence_fermee_mais_pas_un_enseignant_avec_role_direction(): void
    {
        $prof = $this->enseignant('Prof direction', 'prof.direction@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        $this->sequenceActive->update(['saisie_ouverte' => false]);
        $corps = ['sequence_id' => $this->sequenceActive->id,
            'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]]];
        $url = "/api/v1/classe-matieres/{$attribution->id}/notes";
        $this->actingAs($this->admin, 'sanctum')->postJson($url, $corps)->assertOk();
        $prof->assignRole('super_admin');
        $corps['notes'][0]['valeur'] = 7;
        $this->actingAs($prof, 'sanctum')->postJson($url, $corps)->assertForbidden();
        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'valeur' => 15]);
    }

    // ----------------------------------------------------------- Secondaire

    public function test_un_enseignant_peut_noter_le_trimestre_actif(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $prof = $this->enseignant('Prof Math', 'prof.math@test.local');
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceActive->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $this->sequenceActive->id,
        ]);
    }

    public function test_un_enseignant_ne_peut_pas_noter_un_trimestre_clos(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e B']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE DEUX', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Français', 'statut' => 'actif']);
        $prof = $this->enseignant('Prof Français', 'prof.francais@test.local');
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceClose->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
        ]);
    }

    public function test_la_direction_peut_corriger_un_trimestre_clos(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e C']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE TROIS', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Histoire', 'statut' => 'actif']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceClose->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 12]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $this->sequenceClose->id,
        ]);
    }

    // ---------------------------------------------------- Primaire/maternelle

    private function classeCompetencePrimaire(User $titulaire): ClasseCompetence
    {
        $classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CE1-A', 'titulaire_id' => $titulaire->personnel->id,
        ]);
        $competence = Competence::create([
            'school_id' => $this->school->id, 'label_fr' => 'Langue et communication',
        ]);

        // Le barème vit sur l'attribution, pas sur la compétence : c'est elle
        // qui dit sur quoi cette classe-là note.
        return ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id,
            'notation' => 20, 'evalue_pratique' => false,
            'repartition_volets' => ['oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5],
        ]);
    }

    public function test_un_titulaire_peut_noter_le_trimestre_actif_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CE1', 'titulaire.ce1@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($titulaire, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceActive->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
            'sequence_id' => $this->sequenceActive->id,
        ]);
    }

    public function test_un_titulaire_ne_peut_pas_noter_un_trimestre_clos_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CE2', 'titulaire.ce2@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE DEUX', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($titulaire, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceClose->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
        ]);
    }

    public function test_la_direction_peut_corriger_un_trimestre_clos_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CM1', 'titulaire.cm1@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE TROIS', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceClose->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
            'sequence_id' => $this->sequenceClose->id,
        ]);
    }
}
