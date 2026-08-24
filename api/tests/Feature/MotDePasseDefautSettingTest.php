<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\CompteParentService;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le mot de passe par défaut des comptes créés automatiquement (personnel,
 * parents) est un réglage d'établissement (cf. SettingsCatalog), pas une
 * variable d'environnement : le bug qui a motivé ce fichier était justement
 * qu'un `.env` de production, inaccessible sans le devops, avait cette
 * variable vide — tous les nouveaux comptes recevaient un mot de passe haché
 * à partir d'une chaîne vide, introuvable pour quiconque.
 */
class MotDePasseDefautSettingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->root->assignRole('super_admin');
    }

    /** Sans réglage explicite, on retombe sur le défaut du catalogue — jamais une chaîne vide. */
    public function test_sans_reglage_le_defaut_du_catalogue_s_applique(): void
    {
        $personnel = app(PersonnelService::class)->create($this->school->id, [
            'nom_complet' => 'AGBORNDE CATHERINE', 'statut' => 'actif',
        ]);

        $this->assertTrue(Hash::check('Elite@2026', $personnel->user->password));
    }

    /**
     * Le cœur du correctif : un super admin change ce mot de passe depuis
     * Paramètres, sans toucher au serveur, et les comptes créés ensuite le
     * suivent aussitôt.
     */
    public function test_le_super_admin_redefinit_le_mot_de_passe_par_defaut_depuis_les_parametres(): void
    {
        $this->actingAs($this->root, 'sanctum')
            ->putJson('/api/v1/settings', ['settings' => ['mot_de_passe_defaut' => 'Bertoua2027']])
            ->assertOk();

        $this->actingAs($this->root, 'sanctum')
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonFragment(['key' => 'mot_de_passe_defaut', 'value' => 'Bertoua2027']);

        $personnel = app(PersonnelService::class)->create($this->school->id, [
            'nom_complet' => 'AGBORNDE CATHERINE', 'statut' => 'actif',
        ]);
        $this->assertTrue(Hash::check('Bertoua2027', $personnel->user->password));

        $tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'ACHU EDMUND', 'telephone' => '675822844']);
        $compteParent = app(CompteParentService::class)->assurer($tuteur);
        $this->assertTrue(Hash::check('Bertoua2027', $compteParent->password));
    }

    /** Une valeur vide n'écrase jamais le réglage : la porte d'entrée du bug d'origine reste fermée. */
    public function test_une_valeur_vide_n_efface_pas_le_mot_de_passe_par_defaut(): void
    {
        Setting::set($this->school->id, 'mot_de_passe_defaut', 'Bertoua2027');

        $this->actingAs($this->root, 'sanctum')
            ->putJson('/api/v1/settings', ['settings' => ['mot_de_passe_defaut' => '']])
            ->assertOk();

        $this->assertSame('Bertoua2027', Setting::get($this->school->id, 'mot_de_passe_defaut'));
    }

    /** Réglage par établissement : changer celui d'une école ne touche pas les autres. */
    public function test_le_reglage_est_propre_a_chaque_ecole(): void
    {
        $autreEcole = School::create(['name' => 'Autre École', 'code' => 'AE', 'type' => 'secondaire', 'is_active' => true]);

        Setting::set($this->school->id, 'mot_de_passe_defaut', 'Bertoua2027');

        $personnelAutreEcole = app(PersonnelService::class)->create($autreEcole->id, [
            'nom_complet' => 'HORS PERIMETRE', 'statut' => 'actif',
        ]);

        $this->assertTrue(Hash::check('Elite@2026', $personnelAutreEcole->user->password));
    }
}
