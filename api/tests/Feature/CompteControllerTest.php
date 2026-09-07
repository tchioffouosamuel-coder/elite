<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Complexe;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    /**
     * Le compte racine (super admin sans `school_id`, cf.
     * CompteController::comptesAccessibles()) n'a aucune école principale à
     * comparer : la restriction « même complexe » ne doit donc jamais
     * l'empêcher de s'attribuer l'accès à une école, quelle qu'elle soit.
     */
    public function test_le_super_admin_racine_peut_sattribuer_nimporte_quelle_ecole(): void
    {
        $ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $racine = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => null, 'is_active' => true,
        ]);
        $racine->assignRole('super_admin');

        $reponse = $this->actingAs($racine)->putJson("/api/v1/comptes-utilisateurs/{$racine->id}/ecoles", [
            'school_ids' => [$ecole->id],
        ]);

        $reponse->assertOk();
        $this->assertTrue($racine->fresh()->schools->pluck('id')->contains($ecole->id));
    }

    /** Un compte rattaché à une école reste restreint aux écoles du même complexe. */
    public function test_un_compte_avec_ecole_principale_reste_restreint_a_son_complexe(): void
    {
        $complexe = Complexe::create(['name' => 'Complexe A', 'code' => 'CA']);
        $ecolePrincipale = School::create(['complexe_id' => $complexe->id, 'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $ecoleAutreComplexe = School::create(['name' => 'Autre complexe', 'code' => 'AC', 'type' => 'primaire', 'is_active' => true]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $ecolePrincipale->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $reponse = $this->actingAs($admin)->putJson("/api/v1/comptes-utilisateurs/{$admin->id}/ecoles", [
            'school_ids' => [$ecoleAutreComplexe->id],
        ]);

        $reponse->assertStatus(422);
    }

    /**
     * Le bouton de rattrapage (comptes ouverts de longue date, jamais
     * retirés par leur titulaire) ne doit toucher que les comptes SANS
     * aucune entrée `ActivityLog` d'action `connexion` — ni un compte déjà
     * connecté au moins une fois, ni l'administrateur qui déclenche
     * l'action lui-même, même si lui non plus n'a jamais techniquement de
     * ligne `connexion` dans ce test (`actingAs` ne journalise rien).
     *
     * Le compte racine créé par la migration `create_super_admin_user`
     * existe dans CHAQUE environnement (y compris ici, via `RefreshDatabase`)
     * et porte lui aussi le rôle `super_admin` sans jamais s'être connecté
     * dans ce test — on lui donne donc une entrée `connexion` pour l'exclure
     * du calcul, comme le serait en pratique un compte racine réellement
     * utilisé, plutôt que de fausser le total attendu ci-dessous.
     */
    public function test_reinitialise_les_mots_de_passe_des_comptes_jamais_connectes(): void
    {
        ActivityLog::enregistrer(User::where('email', 'admin@elites-school.test')->firstOrFail(), 'connexion', 'Connexion à l’application.');

        $ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $ecole->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $jamaisConnecte = User::create([
            'name' => 'Jamais Connecte', 'email' => 'jc@test.local', 'password' => 'ancien-mdp',
            'school_id' => $ecole->id, 'is_active' => true, 'doit_changer_mot_de_passe' => false,
        ]);

        $dejaConnecte = User::create([
            'name' => 'Deja Connecte', 'email' => 'dc@test.local', 'password' => 'ancien-mdp',
            'school_id' => $ecole->id, 'is_active' => true,
        ]);
        ActivityLog::enregistrer($dejaConnecte, 'connexion', 'Connexion à l’application.');

        $reponse = $this->actingAs($admin)->postJson('/api/v1/comptes-utilisateurs/reinitialiser-mots-de-passe-jamais-connectes');

        $reponse->assertOk();
        $this->assertSame(1, $reponse->json('data.total'));

        $this->assertTrue(Hash::check('Elite@2026', $jamaisConnecte->fresh()->password));
        $this->assertTrue($jamaisConnecte->fresh()->doit_changer_mot_de_passe);

        // Déjà connecté : mot de passe intact.
        $this->assertTrue(Hash::check('ancien-mdp', $dejaConnecte->fresh()->password));

        // L'administrateur qui déclenche l'action ne se réinitialise jamais lui-même.
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }
}
