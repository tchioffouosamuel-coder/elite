<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\AppreciationSequence;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\BulletinService;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Point 16 de la punch-list : appréciation courte (60 caractères) par élève,
 * sur la matière de l'enseignant, pour la 1re ou la 2e séquence d'un
 * trimestre — les deux affichées ensemble sur le bulletin du premier
 * trimestre.
 */
class AppreciationSequenceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Trimestre $trimestre1;

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

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->trimestre1 = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $this->sequence1 = Sequence::create(['trimestre_id' => $this->trimestre1->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);
        $this->sequence2 = Sequence::create(['trimestre_id' => $this->trimestre1->id, 'libelle' => 'Séquence 2', 'ordre' => 2]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function enseignant(string $nom, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
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

    private function classeMatiereAvecEleve(User $prof): array
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e App']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE APPR', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Preinscription::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'eleve_id' => $eleve->id, 'type' => 'existant', 'statut' => 'validee',
            'donnees_eleve' => [], 'donnees_tuteurs' => [],
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais', 'statut' => 'actif']);
        $attribution = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        return [$attribution, $eleve];
    }

    public function test_un_enseignant_enregistre_une_appreciation_de_60_caracteres_maximum(): void
    {
        $prof = $this->enseignant('Prof Appr', 'prof.appr@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);

        $texte = str_repeat('a', 60);
        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$attribution->id}/appreciations-sequence", [
                'sequence_id' => $this->sequence1->id,
                'appreciations' => [['eleve_id' => $eleve->id, 'texte' => $texte]],
            ])->assertOk();

        $this->assertDatabaseHas('appreciations_sequence', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence1->id, 'texte' => $texte,
        ]);
    }

    public function test_un_texte_de_plus_de_60_caracteres_est_rejete(): void
    {
        $prof = $this->enseignant('Prof Long', 'prof.long@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$attribution->id}/appreciations-sequence", [
                'sequence_id' => $this->sequence1->id,
                'appreciations' => [['eleve_id' => $eleve->id, 'texte' => str_repeat('a', 61)]],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['appreciations.0.texte']);

        $this->assertDatabaseCount('appreciations_sequence', 0);
    }

    public function test_un_enseignant_ne_peut_pas_ecrire_sur_une_matiere_qui_nest_pas_la_sienne(): void
    {
        $prof = $this->enseignant('Prof A', 'prof.a@test.local');
        $autre = $this->enseignant('Prof B', 'prof.b@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);

        $this->actingAs($autre, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$attribution->id}/appreciations-sequence", [
                'sequence_id' => $this->sequence1->id,
                'appreciations' => [['eleve_id' => $eleve->id, 'texte' => 'Bon travail']],
            ])->assertForbidden();

        $this->assertDatabaseCount('appreciations_sequence', 0);
    }

    public function test_seules_les_sequences_1_et_2_acceptent_une_appreciation(): void
    {
        $prof = $this->enseignant('Prof Seq3', 'prof.seq3@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        $sequence3 = Sequence::create(['trimestre_id' => $this->trimestre1->id, 'libelle' => 'Séquence 3', 'ordre' => 3]);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$attribution->id}/appreciations-sequence", [
                'sequence_id' => $sequence3->id,
                'appreciations' => [['eleve_id' => $eleve->id, 'texte' => 'Peut mieux faire']],
            ])->assertStatus(422);

        $this->assertDatabaseCount('appreciations_sequence', 0);
    }

    public function test_les_deux_appreciations_apparaissent_ensemble_sur_le_bulletin_du_premier_trimestre(): void
    {
        $prof = $this->enseignant('Prof Bulletin', 'prof.bulletin@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);

        AppreciationSequence::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence1->id, 'texte' => 'Bon départ',
            'saisi_par' => $prof->personnel->id,
        ]);
        AppreciationSequence::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence2->id, 'texte' => 'Confirme ses progrès',
            'saisi_par' => $prof->personnel->id,
        ]);

        $bulletin = app(BulletinService::class)
            ->donneesClasse($attribution->classe, $this->trimestre1);

        $ligne = collect($bulletin['eleves'][0]['groupes'])->flatten(1)->first();

        $this->assertSame('Bon départ', $ligne['appreciation_sequence_1']);
        $this->assertSame('Confirme ses progrès', $ligne['appreciation_sequence_2']);
    }

    public function test_les_appreciations_de_sequence_sont_absentes_du_bulletin_dun_autre_trimestre(): void
    {
        $prof = $this->enseignant('Prof T2', 'prof.t2@test.local');
        [$attribution, $eleve] = $this->classeMatiereAvecEleve($prof);
        $trimestre2 = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 2', 'ordre' => 2,
            'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27',
        ]);

        AppreciationSequence::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id,
            'sequence_id' => $this->sequence1->id, 'texte' => 'Bon départ',
            'saisi_par' => $prof->personnel->id,
        ]);

        $bulletin = app(BulletinService::class)
            ->donneesClasse($attribution->classe, $trimestre2);

        $ligne = collect($bulletin['eleves'][0]['groupes'])->flatten(1)->first();

        $this->assertNull($ligne['appreciation_sequence_1']);
        $this->assertNull($ligne['appreciation_sequence_2']);
    }
}
