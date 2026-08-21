<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rattrapage des accès parent depuis l'écran « Comptes parents ».
 *
 * Le cas qui posait problème : un super admin en mode agrégé (« Toutes les
 * écoles ») voit la liste de tous les tuteurs du complexe, mais le bouton
 * « Ouvrir tous les accès manquants » refusait le lot faute d'école unique.
 */
class CompteParentLotTest extends TestCase
{
    use RefreshDatabase;

    private School $maternelle;

    private School $primaire;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->maternelle = School::create([
            'name' => 'Elites Maternelle', 'code' => 'EM', 'type' => 'maternelle', 'is_active' => true,
        ]);
        $this->primaire = School::create([
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
        ]);
    }

    private function tuteur(School $school, string $nom, ?string $telephone): Tuteur
    {
        return Tuteur::create([
            'school_id' => $school->id,
            'nom_complet' => $nom,
            'telephone' => $telephone,
        ]);
    }

    private function superAdmin(): User
    {
        $root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->maternelle->id, 'is_active' => true,
        ]);
        $root->assignRole('super_admin');

        return $root;
    }

    /** Le lot suit le périmètre de la liste affichée : tout le complexe, pas une seule école. */
    public function test_le_super_admin_en_mode_agrege_ouvre_les_acces_de_toutes_les_ecoles(): void
    {
        $this->tuteur($this->maternelle, 'ACHU EDMUND', '675822844');
        $this->tuteur($this->primaire, 'ADAMOU OUSSOUMANOU', '691215868');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot')
            ->assertOk()
            ->assertJsonPath('data.crees', 2);

        $this->assertSame(2, Tuteur::whereNotNull('user_id')->count());
        $this->assertSame(
            [$this->maternelle->id, $this->primaire->id],
            Tuteur::orderBy('school_id')->pluck('user_id')->map(
                fn ($userId) => User::find($userId)->school_id
            )->sort()->values()->all(),
        );
    }

    /** Un `school_id` explicite restreint le lot à cette école. */
    public function test_une_ecole_precisee_restreint_le_lot(): void
    {
        $this->tuteur($this->maternelle, 'ACHU EDMUND', '675822844');
        $this->tuteur($this->primaire, 'ADAMOU OUSSOUMANOU', '691215868');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot', ['school_id' => $this->primaire->id])
            ->assertOk()
            ->assertJsonPath('data.crees', 1);

        $this->assertNull(Tuteur::where('school_id', $this->maternelle->id)->first()->user_id);
        $this->assertNotNull(Tuteur::where('school_id', $this->primaire->id)->first()->user_id);
    }

    /**
     * Une fiche sans numéro n'interrompt pas le lot : elle est signalée dans
     * `ignores` et les autres familles reçoivent quand même leur accès.
     */
    public function test_un_tuteur_sans_numero_est_ignore_sans_bloquer_les_autres(): void
    {
        $this->tuteur($this->maternelle, 'SANS NUMERO', null);
        $this->tuteur($this->maternelle, 'ACHU EDMUND', '675822844');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot')
            ->assertOk()
            ->assertJsonPath('data.crees', 1)
            ->assertJsonPath('data.ignores.0.tuteur', 'SANS NUMERO');
    }
}
