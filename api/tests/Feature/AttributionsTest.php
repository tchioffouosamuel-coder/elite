<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\Attributions;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Responsabilités nominatives : ce qu'un agent peut faire, et **où**.
 *
 * Les deux questions étaient confondues jusqu'ici — le privilège suffisait,
 * quelle que soit la classe. Ces cas reprennent les situations réelles d'un
 * établissement secondaire, telles que _smapp les traitait :
 *
 * - un enseignant désigné surveillant général d'une classe ;
 * - un agent dont la fonction *est* surveillant général, qui n'enseigne pas ;
 * - un professeur principal, enseignant avant tout ;
 * - un censeur, par fonction ou par désignation ;
 * - un chef de département.
 */
class AttributionsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Niveau $niveau;

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

        $this->niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary']);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-15',
            'is_active' => true,
        ]);
    }

    private function classe(string $nom): Classe
    {
        return Classe::create([
            'school_id' => $this->school->id,
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'nom' => $nom,
        ]);
    }

    /**
     * Agent doté d'une fonction du référentiel, dont le groupe de privilèges
     * reprend celui du rôle correspondant — exactement ce que le seeder pose
     * en production.
     */
    private function agent(string $labelFonction, string $role, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id,
            'label_fr' => $labelFonction,
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS[$role]);

        $user = User::create([
            'name' => $labelFonction, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        Personnel::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => $labelFonction.' de test',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function enseigne(User $user, Classe $classe, string $matiere = 'Mathématiques'): void
    {
        $matiere = Matiere::firstOrCreate(
            ['school_id' => $this->school->id, 'nom' => $matiere],
            ['statut' => 'actif'],
        );

        ClasseMatiere::create([
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
            'personnel_id' => $user->personnel->id,
            'statut' => 'actif',
        ]);
    }

    // --- Surveillant général, premier cas : un enseignant désigné -----------

    public function test_un_enseignant_designe_surveillant_general_cumule_les_deux_casquettes(): void
    {
        $enseignee = $this->classe('6e A');
        $surveillee = $this->classe('6e B');

        $user = $this->agent('Enseignant', 'enseignant', 'prof.sg@test.local');
        $this->enseigne($user, $enseignee);
        $surveillee->update(['surveillant_general_id' => $user->personnel->id]);

        $user = $user->fresh();

        // Sa fonction ne lui donne pas la discipline ; son attribution, si.
        $this->assertFalse($user->permissionsDeBase()->contains('discipline.manage'));
        $this->assertTrue($user->aLaPermission('discipline.manage'));

        // Mais seulement sur la classe qu'il surveille.
        $this->assertTrue($user->peutSurClasse('discipline.manage', $surveillee->id));
        $this->assertFalse($user->peutSurClasse('discipline.manage', $enseignee->id));

        // Et il reste enseignant là où il enseigne — pas là où il surveille.
        $this->assertTrue($user->peutSurClasse('notes.create', $enseignee->id));
        $this->assertFalse($user->peutSurClasse('notes.create', $surveillee->id));
    }

    // --- Surveillant général, second cas : c'est sa fonction ---------------

    public function test_un_surveillant_general_de_fonction_n_enseigne_pas_et_ne_tient_que_ses_classes(): void
    {
        $assignee = $this->classe('5e A');
        $autre = $this->classe('5e B');

        $user = $this->agent('Surveillant Général', 'surveillant_general', 'sg@test.local');
        $assignee->update(['surveillant_general_id' => $user->personnel->id]);

        $user = $user->fresh();

        $this->assertFalse($user->estEnseignant());
        $this->assertFalse($user->aLaPermission('notes.create'));

        $this->assertTrue($user->peutSurClasse('discipline.manage', $assignee->id));
        $this->assertFalse($user->peutSurClasse('discipline.manage', $autre->id));

        // Son périmètre se limite aux classes assignées : rien d'autre ne le regarde.
        $this->assertSame([$assignee->id], $user->perimetre()->classes());
    }

    public function test_un_surveillant_general_sans_classe_assignee_ne_voit_rien(): void
    {
        $this->classe('4e A');
        $user = $this->agent('Surveillant Général', 'surveillant_general', 'sg.vide@test.local');

        $this->assertSame([], $user->perimetre()->classes());
        $this->assertSame([], $user->perimetre()->attributions());
    }

    // --- Professeur principal ----------------------------------------------

    public function test_le_professeur_principal_ajoute_sa_classe_a_ses_prerogatives_d_enseignant(): void
    {
        $principale = $this->classe('3e A');
        $autreEnseignee = $this->classe('3e B');

        $user = $this->agent('Enseignant', 'enseignant', 'pp@test.local');
        $this->enseigne($user, $principale);
        $this->enseigne($user, $autreEnseignee, 'Physique');
        $principale->update(['professeur_principal_id' => $user->personnel->id]);

        $user = $user->fresh();

        // Enseignant ordinaire dans les deux classes où il intervient.
        $this->assertTrue($user->peutSurClasse('notes.create', $principale->id));
        $this->assertTrue($user->peutSurClasse('notes.create', $autreEnseignee->id));

        // Professeur principal dans la seule classe qui lui est confiée :
        // c'est là qu'il règle les affectations et les coefficients.
        $this->assertTrue($user->peutSurClasse('pedagogie.manage', $principale->id));
        $this->assertFalse($user->peutSurClasse('pedagogie.manage', $autreEnseignee->id));

        $this->assertTrue($user->perimetre()->aLAttribution(Attributions::PROFESSEUR_PRINCIPAL, $principale->id));
    }

    // --- Censeur : par désignation ou par fonction --------------------------

    public function test_un_enseignant_nomme_censeur_pilote_le_pedagogique_de_son_groupe_de_classes(): void
    {
        $censurees = [$this->classe('2nde A'), $this->classe('2nde B')];
        $enseignee = $this->classe('2nde C');

        $user = $this->agent('Enseignant', 'enseignant', 'prof.censeur@test.local');
        $this->enseigne($user, $enseignee);
        foreach ($censurees as $classe) {
            $classe->update(['censeur_id' => $user->personnel->id]);
        }

        $user = $user->fresh();

        $this->assertTrue($user->peutSurClasse('bulletins.publish', $censurees[0]->id));
        $this->assertTrue($user->peutSurClasse('bulletins.publish', $censurees[1]->id));
        $this->assertFalse($user->peutSurClasse('bulletins.publish', $enseignee->id));

        // Le censorat est pédagogique : la discipline reste au surveillant général.
        $this->assertFalse($user->peutSurClasse('discipline.manage', $censurees[0]->id));
    }

    public function test_un_censeur_de_fonction_suit_la_meme_logique_sur_ses_classes(): void
    {
        $censuree = $this->classe('1ere A');
        $autre = $this->classe('1ere B');

        $user = $this->agent('Censeur', 'censeur_sg', 'censeur@test.local');
        $censuree->update(['censeur_id' => $user->personnel->id]);

        $user = $user->fresh();

        $this->assertTrue($user->peutSurClasse('notes.create', $censuree->id));
        $this->assertFalse($user->peutSurClasse('notes.create', $autre->id));
    }

    // --- Chef de département ------------------------------------------------

    public function test_le_chef_de_departement_couvre_les_classes_ou_ses_matieres_sont_enseignees(): void
    {
        $avecMatiere = $this->classe('6e C');
        $sansMatiere = $this->classe('6e D');

        $user = $this->agent('Enseignant', 'enseignant', 'chef.dept@test.local');

        $departement = Departement::create(['school_id' => $this->school->id, 'nom' => 'Sciences']);
        $departement->update(['head_personnel_id' => $user->personnel->id]);

        $matiere = Matiere::create([
            'school_id' => $this->school->id,
            'departement_id' => $departement->id,
            'nom' => 'SVT',
            'statut' => 'actif',
        ]);
        ClasseMatiere::create([
            'classe_id' => $avecMatiere->id,
            'matiere_id' => $matiere->id,
            'statut' => 'actif',
        ]);

        $user = $user->fresh();

        $this->assertSame([$departement->id], $user->perimetre()->departementsDiriges());
        $this->assertTrue($user->peutSurClasse('pedagogie.manage', $avecMatiere->id));
        $this->assertFalse($user->peutSurClasse('pedagogie.manage', $sansMatiere->id));
    }

    // --- Bornage des listes et des routes ------------------------------------

    public function test_la_liste_des_classes_se_borne_au_perimetre(): void
    {
        $surveillee = $this->classe('6e E');
        $this->classe('6e F');

        $user = $this->agent('Surveillant Général', 'surveillant_general', 'sg.liste@test.local');
        $surveillee->update(['surveillant_general_id' => $user->personnel->id]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $surveillee->id);
    }

    public function test_une_classe_hors_perimetre_est_refusee_meme_avec_le_privilege(): void
    {
        $surveillee = $this->classe('5e C');
        $hors = $this->classe('5e D');

        $user = $this->agent('Surveillant Général', 'surveillant_general', 'sg.route@test.local');
        $surveillee->update(['surveillant_general_id' => $user->personnel->id]);
        $user = $user->fresh();

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/classes/{$surveillee->id}")->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/classes/{$hors->id}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_l_enseignant_ne_voit_que_les_eleves_de_ses_classes(): void
    {
        $sienne = $this->classe('3e C');
        $autre = $this->classe('3e D');

        $user = $this->agent('Enseignant', 'enseignant', 'prof.eleves@test.local');
        $this->enseigne($user, $sienne);

        foreach ([[$sienne, 'Élève de sa classe'], [$autre, "Élève d'ailleurs"]] as [$classe, $nom]) {
            Eleve::create([
                'school_id' => $this->school->id,
                'classe_id' => $classe->id,
                'matricule' => Eleve::genererMatricule($this->school->id),
                'nom_complet' => $nom,
                'sexe' => 'F',
                'statut' => 'actif',
            ]);
        }

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/eleves')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom_complet', 'Élève de sa classe');
    }

    public function test_l_enseignant_garde_l_acces_a_ses_propres_classes(): void
    {
        $sienne = $this->classe('4e C');
        $autre = $this->classe('4e D');

        $user = $this->agent('Enseignant', 'enseignant', 'prof.acces@test.local');
        $this->enseigne($user, $sienne);
        $user = $user->fresh();

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/classes/{$sienne->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/classes/{$autre->id}")->assertStatus(403);
    }

    public function test_la_direction_n_est_pas_bornee(): void
    {
        $this->classe('6e G');
        $this->classe('6e H');

        $directeur = $this->agent('Principal', 'admin_etablissement', 'principal@test.local');

        $this->assertFalse($directeur->perimetre()->estBorne());
        $this->assertNull($directeur->perimetre()->classes());

        $this->actingAs($directeur, 'sanctum')->getJson('/api/v1/classes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_l_economat_reste_hors_bornage(): void
    {
        $this->classe('6e I');

        $econome = $this->agent('Économe', 'econome', 'econome@test.local');

        // Sa fonction ne porte sur aucune classe en particulier : lui appliquer
        // un périmètre de classes viderait la caisse de ses élèves.
        $this->assertFalse($econome->perimetre()->estBorne());
    }

    // --- Restitution aux clients --------------------------------------------

    public function test_le_compte_expose_ses_attributions(): void
    {
        $surveillee = $this->classe('4e B');

        $user = $this->agent('Enseignant', 'enseignant', 'expose@test.local');
        $surveillee->update(['surveillant_general_id' => $user->personnel->id]);

        $reponse = $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(Attributions::SURVEILLANT_GENERAL, $reponse->json('data.attributions.0.code'));
        $this->assertSame([$surveillee->id], $reponse->json('data.attributions.0.classes'));
        $this->assertTrue($reponse->json('data.perimetre_borne'));

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/mes-attributions')
            ->assertOk()
            ->assertJsonPath('data.0.code', Attributions::SURVEILLANT_GENERAL)
            ->assertJsonPath('data.0.classes.0.nom', '4e B');
    }

    public function test_seules_les_fonctions_eligibles_sont_proposees_pour_une_attribution(): void
    {
        $this->agent('Enseignant', 'enseignant', 'e1@test.local');
        $this->agent('Surveillant Général', 'surveillant_general', 'sg1@test.local');
        $this->agent('Économe', 'econome', 'ec1@test.local');
        $principal = $this->agent('Principal', 'admin_etablissement', 'p1@test.local');

        $reponse = $this->actingAs($principal, 'sanctum')
            ->getJson('/api/v1/personnels?attribution='.Attributions::SURVEILLANT_GENERAL)
            ->assertOk();

        $fonctions = collect($reponse->json('data'))->pluck('fonction')->sort()->values()->all();

        $this->assertSame(['Enseignant', 'Surveillant Général'], $fonctions);
    }

    public function test_le_professeur_principal_ne_se_recrute_que_parmi_les_enseignants(): void
    {
        $this->agent('Enseignant', 'enseignant', 'e2@test.local');
        $this->agent('Censeur', 'censeur_sg', 'c2@test.local');
        $principal = $this->agent('Principal', 'admin_etablissement', 'p2@test.local');

        $reponse = $this->actingAs($principal, 'sanctum')
            ->getJson('/api/v1/personnels?attribution='.Attributions::PROFESSEUR_PRINCIPAL)
            ->assertOk();

        $this->assertSame(['Enseignant'], collect($reponse->json('data'))->pluck('fonction')->all());
    }
}
