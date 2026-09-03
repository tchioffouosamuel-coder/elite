<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CompteEleveService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ouverture des accès du portail élève — pendant de CompteParentLotTest pour
 * le portail parent, avec la particularité du matricule comme identifiant
 * (cf. CompteEleveService).
 */
class CompteEleveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'eleve', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
    }

    private function eleve(string $matricule, ?string $nom = null): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'matricule' => $matricule,
            'nom_complet' => $nom ?? "Eleve {$matricule}",
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function service(): CompteEleveService
    {
        return app(CompteEleveService::class);
    }

    /** Ouvrir l'accès crée le compte et lie la fiche ; le rejouer ne crée pas de doublon. */
    public function test_assurer_est_idempotent(): void
    {
        $eleve = $this->eleve('ET-2026-001');

        $user1 = $this->service()->assurer($eleve);
        $this->assertTrue($user1->hasRole('eleve'));
        $this->assertTrue($user1->doit_changer_mot_de_passe);

        $user2 = $this->service()->assurer($eleve->fresh());

        $this->assertSame($user1->id, $user2->id);
        $this->assertSame(1, User::whereHas('roles', fn ($q) => $q->where('name', 'eleve'))->count());
    }

    /** Sans matricule, impossible d'ouvrir un accès : rien à utiliser comme identifiant de connexion. */
    public function test_assurer_echoue_sans_matricule(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Sans Matricule', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service()->assurer($eleve);
    }

    /**
     * Le matricule n'est unique que par école (compteur remis à zéro par
     * établissement) : deux écoles peuvent légitimement générer le même
     * matricule, mais un seul des deux peut avoir un compte actif dessus.
     */
    public function test_deux_eleves_avec_le_meme_matricule_ne_peuvent_pas_tous_deux_avoir_un_compte(): void
    {
        $autreEcole = School::create(['name' => 'Elites Bis', 'code' => 'EB', 'type' => 'secondaire', 'is_active' => true]);

        $premier = $this->eleve('DOUBLON-01');
        $second = Eleve::create([
            'school_id' => $autreEcole->id, 'matricule' => 'DOUBLON-01', 'nom_complet' => 'Second Eleve', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->service()->assurer($premier);

        $this->expectException(\RuntimeException::class);

        $this->service()->assurer($second);
    }

    /** Une fois l'accès ouvert, l'élève se connecte avec son matricule comme identifiant. */
    public function test_connexion_par_matricule(): void
    {
        $eleve = $this->eleve('ET-2026-042', 'Test Connexion');
        $user = $this->service()->assurer($eleve);
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('motdepasse')])->save();

        $resultat = app(AuthService::class)->login('ET-2026-042', 'motdepasse');

        $this->assertNotNull($resultat);
        $this->assertSame($user->id, $resultat['user']->id);
    }

    /** Le lot ouvre les accès de tous les élèves avec matricule, et journalise ceux qu'il ignore. */
    public function test_assurer_lot_traite_toute_lecole_et_ignore_les_fiches_sans_matricule(): void
    {
        $this->eleve('ET-2026-001');
        $this->eleve('ET-2026-002');
        Eleve::create(['school_id' => $this->school->id, 'nom_complet' => 'Sans Matricule', 'sexe' => 'F', 'statut' => 'actif']);

        $resultat = $this->service()->assurerLot($this->school->id);

        $this->assertSame(2, $resultat['crees']);
        $this->assertCount(1, $resultat['ignores']);
        $this->assertSame('Sans Matricule', $resultat['ignores'][0]['eleve']);
    }

    /** L'écran « Comptes élèves » (admin) ouvre un accès individuel via la route HTTP. */
    public function test_route_admin_ouvre_un_compte_eleve(): void
    {
        $eleve = $this->eleve('ET-2026-100', 'Route Admin');

        $admin = User::create([
            'school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin@elites.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $admin->givePermissionTo('eleves.manage');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/eleves/{$eleve->id}/compte-eleve")
            ->assertOk()
            ->assertJsonPath('data.identifiant', 'ET-2026-100');

        $this->assertNotNull($eleve->fresh()->user_id);
    }

    /** Bloquer l'accès désactive le compte et révoque ses jetons ; le rebasculer le réactive. */
    public function test_route_admin_bascule_lacces(): void
    {
        $eleve = $this->eleve('ET-2026-200', 'Bascule Acces');
        $user = $this->service()->assurer($eleve);

        $admin = User::create([
            'school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin2@elites.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $admin->givePermissionTo('eleves.manage');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/eleves/{$eleve->id}/basculer-acces")
            ->assertOk();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/eleves/{$eleve->id}/basculer-acces")
            ->assertOk();

        $this->assertTrue($user->fresh()->is_active);
    }
}
