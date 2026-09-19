<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `GET /eleves/doublons` sépare les doublons « certains » (même nom, même
 * école, même date de naissance exacte) des « potentiels » (même nom, même
 * école, date de naissance manquante ou différente) — ces derniers étaient
 * jusqu'ici invisibles de la page, alors qu'ils restent le cas le plus
 * fréquent laissé par un import massif mal dédupliqué.
 */
class EleveDoublonsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleve(string $matricule, ?string $dateNaissance): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'matricule' => $matricule,
            'nom_complet' => 'ABDALLAH MOHAMADOU',
            'sexe' => 'M',
            'date_naissance' => $dateNaissance,
            'statut' => 'actif',
        ]);
    }

    private function appeler(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/eleves/doublons')
            ->assertOk()
            ->json('data');
    }

    public function test_meme_date_de_naissance_va_dans_certains(): void
    {
        $this->eleve('25ELITES-0001', '2013-08-15');
        $this->eleve('25ELITES-0002', '2013-08-15');

        $data = $this->appeler();

        $this->assertCount(1, $data['certains']);
        $this->assertCount(0, $data['potentiels']);
        $this->assertCount(2, $data['certains'][0]['membres']);
    }

    public function test_date_de_naissance_manquante_sur_une_fiche_va_dans_potentiels(): void
    {
        $this->eleve('25ELITES-0009', '2013-08-15');
        $this->eleve('25ELITES-0010', null);

        $data = $this->appeler();

        $this->assertCount(0, $data['certains']);
        $this->assertCount(1, $data['potentiels']);
        $this->assertCount(2, $data['potentiels'][0]['membres']);
        $this->assertTrue($data['potentiels'][0]['potentiel']);
    }

    public function test_dates_de_naissance_differentes_va_dans_potentiels(): void
    {
        $this->eleve('25ELITES-0009', '2013-08-15');
        $this->eleve('25ELITES-0010', '2014-01-01');

        $data = $this->appeler();

        $this->assertCount(0, $data['certains']);
        $this->assertCount(1, $data['potentiels']);
    }

    public function test_nom_unique_nest_ni_certain_ni_potentiel(): void
    {
        $this->eleve('25ELITES-0001', '2013-08-15');

        $data = $this->appeler();

        $this->assertCount(0, $data['certains']);
        $this->assertCount(0, $data['potentiels']);
    }
}
