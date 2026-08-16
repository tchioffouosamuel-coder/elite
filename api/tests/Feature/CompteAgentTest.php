<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Services\CompteAgentService;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompteAgentTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private FonctionReferentiel $fonction;

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

        $this->fonction = FonctionReferentiel::create([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $this->fonction->synchroniserPermissions(['eleves.view', 'notes.create']);
    }

    private function creer(array $attributs = []): Personnel
    {
        return app(PersonnelService::class)->create($this->school->id, [
            'nom_complet' => 'AGBORNDE CATHERINE BESONG',
            'fonction_id' => $this->fonction->id,
            'statut' => 'actif',
            ...$attributs,
        ]);
    }

    public function test_creer_un_agent_ouvre_son_acces(): void
    {
        $personnel = $this->creer();

        $this->assertNotNull($personnel->user_id);
        $this->assertSame('agbornde.catherine.besong@elite.school', $personnel->user->email);
        $this->assertTrue($personnel->user->is_active);
        $this->assertSame($this->school->id, $personnel->user->school_id);
    }

    public function test_le_mot_de_passe_par_defaut_permet_de_se_connecter(): void
    {
        $user = $this->creer()->user;

        $this->assertTrue(Hash::check(config('personnel.mot_de_passe_defaut'), $user->password));
    }

    /** Le cœur de la demande : les droits viennent de la fonction, et d'elle seule. */
    public function test_le_compte_ne_porte_aucun_role_et_herite_de_la_fonction(): void
    {
        $user = $this->creer()->user->fresh();

        $this->assertCount(0, $user->getRoleNames());
        $this->assertCount(0, $user->getAllPermissions());

        $this->assertEqualsCanonicalizing(['eleves.view', 'notes.create'], $user->permissionsEffectives()->all());
        $this->assertTrue($user->aLaPermission('notes.create'));
        $this->assertFalse($user->aLaPermission('eleves.manage'));
    }

    public function test_deux_homonymes_recoivent_des_adresses_distinctes(): void
    {
        $premier = $this->creer();
        $second = $this->creer();

        $this->assertSame('agbornde.catherine.besong@elite.school', $premier->user->email);
        $this->assertSame('agbornde.catherine.besong.2@elite.school', $second->user->email);
    }

    public function test_une_adresse_saisie_est_conservee(): void
    {
        $personnel = $this->creer(['email' => 'catherine@exemple.test']);

        $this->assertSame('catherine@exemple.test', $personnel->user->email);
    }

    public function test_un_agent_sorti_des_effectifs_ne_recoit_pas_d_acces(): void
    {
        $personnel = $this->creer(['statut' => 'ex_employe']);

        $this->assertNull($personnel->user_id);
        $this->assertSame(0, User::count());
    }

    public function test_l_ouverture_est_idempotente(): void
    {
        $personnel = $this->creer();
        $userId = $personnel->user_id;

        app(CompteAgentService::class)->assurer($personnel->fresh());

        $this->assertSame($userId, $personnel->fresh()->user_id);
        $this->assertSame(1, User::count());
    }
}
