<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'attestation employeur passait par `app('tenant.school_id')` (singulier)
 * au lieu de `Tenant::schoolIds()` : en mode agrégé (super admin, « Toutes
 * les écoles »), ce singulier retombe sur une seule école du complexe — pas
 * forcément celle de l'agent demandé — et `findOrFail` échoue alors en 404,
 * même quand l'agent existe bel et bien dans une autre école accessible.
 */
class PersonnelAttestationEmployeurTest extends TestCase
{
    use RefreshDatabase;

    private School $ecoleCompte;
    private School $autreEcole;
    private User $superAdmin;
    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // L'école « propre » du compte super admin — celle sur laquelle
        // `app('tenant.school_id')` retombait aveuglément — est délibérément
        // différente de celle où travaille l'agent demandé.
        $this->ecoleCompte = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $this->autreEcole = School::create(['name' => 'Elites Bilingual Nursery School', 'code' => 'EBNS', 'type' => 'maternelle', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->ecoleCompte->id, 'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $fonction = FonctionReferentiel::create(['school_id' => $this->autreEcole->id, 'label_fr' => 'Enseignant']);
        $this->agent = app(PersonnelService::class)->create($this->autreEcole->id, [
            'nom_complet' => 'AGBORNDE CATHERINE BESONG',
            'sexe' => 'F',
            'fonction_id' => $fonction->id,
            'statut' => 'actif',
            'date_embauche' => '2017-09-01',
        ]);
    }

    /** Le mode agrégé (aucun X-School-Id) est le cas de production qui produisait le 404. */
    public function test_l_attestation_se_genere_en_mode_agrege_pour_un_agent_d_une_autre_ecole(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->get("/api/v1/personnels/{$this->agent->id}/attestation-employeur")
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );
    }

    /** Toujours vrai en mode « focus » sur l'école de l'agent. */
    public function test_l_attestation_se_genere_avec_x_school_id_sur_l_ecole_de_l_agent(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeader('X-School-Id', $this->autreEcole->id)
            ->get("/api/v1/personnels/{$this->agent->id}/attestation-employeur")
            ->assertOk();
    }
}
