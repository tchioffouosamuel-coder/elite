<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnneeScolaireTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
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

    /**
     * Régression : un libellé déjà pris pour cette école doit être refusé
     * par la validation (422, message exploitable), pas laisser la
     * contrainte SQL échouer et remonter sa requête brute — hôte, port et
     * valeurs liées compris — jusqu'à l'écran du guichet.
     */
    public function test_un_libelle_deja_pris_pour_l_ecole_est_refuse_par_la_validation(): void
    {
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/annees-scolaires', [
                'libelle' => '2026-2027',
                'date_debut' => '2025-07-04',
                'date_fin' => '2026-08-01',
                'is_active' => true,
            ]);

        $reponse->assertStatus(422)->assertJsonValidationErrors('libelle');
        $this->assertSame(1, AnneeScolaire::where('school_id', $this->school->id)->where('libelle', '2026-2027')->count());
    }

    /** Le même libellé reste permis pour une autre école : la contrainte est bornée à `school_id`, pas globale. */
    public function test_le_meme_libelle_reste_permis_pour_une_autre_ecole(): void
    {
        $autreEcole = School::create(['name' => 'Elites Prim', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);
        AnneeScolaire::create([
            'school_id' => $autreEcole->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/annees-scolaires', [
                'libelle' => '2026-2027',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-07-31',
            ]);

        $reponse->assertCreated();
    }
}
