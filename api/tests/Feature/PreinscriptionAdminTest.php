<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\NotificationInterne;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\PreinscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PreinscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Tuteur $tuteur;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.view', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->tuteur = Tuteur::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Mballa Jean', 'telephone' => '699000000',
        ]);
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /** Compte parent branché sur `$this->tuteur`. */
    private function parentUser(): User
    {
        $user = User::create([
            'name' => 'Mballa Jean', 'email' => 'mballa@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('parent');
        $user->givePermissionTo('eleves.view');
        $this->tuteur->update(['user_id' => $user->id]);

        return $user;
    }

    private function soumettrePreinscriptionNouvel(): Preinscription
    {
        return app(PreinscriptionService::class)->soumettre($this->tuteur, [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-01'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000', 'lien_parente' => 'père', 'is_principal' => true]],
        ]);
    }

    public function test_admin_peut_corriger_puis_valider_une_preinscription(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        $admin = $this->admin();

        // Le nom porte une coquille ("Mballa Juniorr") : l'admin la corrige
        // avant de valider, sans avoir à rejeter puis attendre un nouveau dépôt.
        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/preinscriptions/{$preinscription->id}", [
                'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
            ]);

        $reponse->assertOk()->assertJsonPath('data.donnees_eleve.date_naissance', '2015-05-02');

        $validation = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/preinscriptions/{$preinscription->id}/valider");

        $validation->assertOk();

        $eleve = Eleve::where('nom_complet', 'Mballa Junior')->firstOrFail();
        $this->assertSame('2015-05-02', $eleve->date_naissance->format('Y-m-d'));
    }

    public function test_corriger_une_preinscription_deja_traitee_est_refuse(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        app(PreinscriptionService::class)->valider($preinscription);
        $admin = $this->admin();

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/preinscriptions/{$preinscription->id}", [
                'donnees_eleve' => ['nom_complet' => 'Autre nom', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean']],
            ]);

        $reponse->assertStatus(422);
    }

    /** Régression : sans `lien`, la notification créée à la soumission ne menait nulle part côté admin. */
    public function test_la_soumission_notifie_avec_un_lien_exploitable(): void
    {
        $destinataire = User::create([
            'name' => 'Censeur', 'email' => 'censeur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $destinataire->givePermissionTo('eleves.manage');

        $preinscription = $this->soumettrePreinscriptionNouvel();

        $notification = NotificationInterne::where('user_id', $destinataire->id)->where('type', 'preinscription')->firstOrFail();
        $this->assertSame("/preinscriptions?id={$preinscription->id}", $notification->lien);
    }

    /** Régression : un enfant déjà préinscrit ne doit pas pouvoir être préinscrit une seconde fois tant que la demande n'est pas traitée. */
    public function test_le_parent_ne_peut_pas_repreinscrire_un_eleve_deja_en_attente(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC1', 'nom_complet' => 'Mballa Aline',
            'sexe' => 'F', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);
        $user = $this->parentUser();

        $donnees = [
            'type' => 'existant',
            'eleve_id' => $eleve->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Aline', 'sexe' => 'F', 'date_naissance' => '2016-01-01'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ];

        app(PreinscriptionService::class)->soumettre($this->tuteur, $donnees);

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', $donnees);

        $reponse->assertStatus(422);
        $this->assertSame(1, Preinscription::where('eleve_id', $eleve->id)->count());
    }

    /** Même garde-fou pour un enfant pas encore scolarisé, rapproché sur nom + date de naissance faute d'identifiant. */
    public function test_le_parent_ne_peut_pas_redeposer_un_nouvel_enfant_deja_en_attente(): void
    {
        $user = $this->parentUser();

        $donnees = [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2017-03-04'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ];

        app(PreinscriptionService::class)->soumettre($this->tuteur, $donnees);

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', $donnees);

        $reponse->assertStatus(422);
        $this->assertSame(1, Preinscription::where('tuteur_id', $this->tuteur->id)->count());
    }

    /** Le parent corrige sa propre demande en attente plutôt que d'en redéposer une. */
    public function test_le_parent_peut_modifier_sa_preinscription_en_attente(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        $user = $this->parentUser();

        $reponse = $this->actingAs($user, 'sanctum')->putJson("/api/v1/parent/preinscriptions/{$preinscription->id}", [
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
            'montant_verser' => 50000,
        ]);

        $reponse->assertOk()->assertJsonPath('data.statut', 'en_attente');
        $this->assertSame('2015-05-02', $preinscription->fresh()->donnees_eleve['date_naissance']);
        $this->assertSame(50000, $preinscription->fresh()->montant_verser);
    }

    /** Un parent ne peut ni voir ni modifier la préinscription d'un autre compte. */
    public function test_un_parent_ne_peut_pas_modifier_la_preinscription_dun_autre(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();

        $autreTuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Autre Parent', 'telephone' => '699999999']);
        $autreUser = User::create([
            'name' => 'Autre Parent', 'email' => 'autre@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $autreUser->assignRole('parent');
        $autreTuteur->update(['user_id' => $autreUser->id]);

        $reponse = $this->actingAs($autreUser, 'sanctum')->putJson("/api/v1/parent/preinscriptions/{$preinscription->id}", [
            'donnees_eleve' => ['nom_complet' => 'Piraté', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
            'donnees_tuteurs' => [['nom_complet' => 'Autre Parent']],
        ]);

        $reponse->assertStatus(404);
    }
}
