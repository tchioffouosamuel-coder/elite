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

    // --------------------------------------------------------- Découpage en lots

    /** La préparation renvoie le même périmètre agrégé que l'ouverture en une seule requête. */
    public function test_la_preparation_du_lot_liste_les_tuteurs_de_tout_le_complexe(): void
    {
        $a = $this->tuteur($this->maternelle, 'ACHU EDMUND', '675822844');
        $b = $this->tuteur($this->primaire, 'ADAMOU OUSSOUMANOU', '691215868');
        $superAdmin = $this->superAdmin();
        // Déjà pourvu : ne doit pas apparaître dans la liste à traiter.
        $dejaPourvu = $this->tuteur($this->maternelle, 'DEJA POURVU', '699000000');
        $dejaPourvu->forceFill(['user_id' => $superAdmin->id])->save();

        $reponse = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/preparer')
            ->assertOk();

        $this->assertSame([$a->id, $b->id], $reponse->json('data.ids'));
    }

    /** Traiter le lot par petits paquets produit le même résultat qu'un seul gros appel. */
    public function test_traiter_le_lot_par_petits_paquets_ouvre_tous_les_acces(): void
    {
        $a = $this->tuteur($this->maternelle, 'ACHU EDMUND', '675822844');
        $b = $this->tuteur($this->primaire, 'ADAMOU OUSSOUMANOU', '691215868');
        $sansNumero = $this->tuteur($this->maternelle, 'SANS NUMERO', null);

        $superAdmin = $this->superAdmin();

        $ids = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/preparer')
            ->json('data.ids');

        $this->assertSame([$a->id, $b->id, $sansNumero->id], $ids);

        // Un paquet à la fois, comme le ferait le front pour un gros effectif.
        $crees = 0;
        $ignores = [];
        foreach (array_chunk($ids, 1) as $paquet) {
            $reponse = $this->actingAs($superAdmin, 'sanctum')
                ->postJson('/api/v1/tuteurs/comptes-parent-lot/traiter', ['ids' => $paquet])
                ->assertOk();
            $crees += $reponse->json('data.crees');
            $ignores = [...$ignores, ...$reponse->json('data.ignores')];
        }

        $this->assertSame(2, $crees);
        $this->assertSame('SANS NUMERO', $ignores[0]['tuteur']);
        $this->assertNotNull($a->fresh()->user_id);
        $this->assertNotNull($b->fresh()->user_id);
        $this->assertNull($sansNumero->fresh()->user_id);
    }

    /**
     * Un tuteur sans numéro échoue systématiquement : la liste préparée en
     * amont ne doit pas bouger d'un paquet à l'autre à cause de lui, sans
     * quoi il resterait indéfiniment dans un filtre relu à chaque appel.
     */
    public function test_un_tuteur_qui_echoue_ne_fait_pas_boucler_la_liste_preparee(): void
    {
        $sansNumero = $this->tuteur($this->maternelle, 'SANS NUMERO', null);
        $superAdmin = $this->superAdmin();

        $ids = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/preparer')
            ->json('data.ids');

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/traiter', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.crees', 0)
            ->assertJsonPath('data.ignores.0.tuteur', 'SANS NUMERO');

        // Toujours sans compte, mais la liste préparée reste celle d'origine :
        // rejouer /preparer redonne le même id, pas une liste qui grandit.
        $idsApres = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/preparer')
            ->json('data.ids');

        $this->assertSame([$sansNumero->id], $idsApres);
    }

    /** Les ids sont revalidés dans le périmètre courant, pas simplement acceptés tels quels. */
    public function test_traiter_ignore_les_ids_hors_perimetre(): void
    {
        $secondaire = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $horsPerimetre = $this->tuteur($secondaire, 'HORS PERIMETRE', '677000000');

        $focus = $this->superAdmin();

        $this->actingAs($focus, 'sanctum')
            ->withHeader('X-School-Id', $this->maternelle->id)
            ->postJson('/api/v1/tuteurs/comptes-parent-lot/traiter', ['ids' => [$horsPerimetre->id]])
            ->assertOk()
            ->assertJsonPath('data.crees', 0)
            ->assertJsonPath('data.ignores', []);

        $this->assertNull($horsPerimetre->fresh()->user_id);
    }
}
