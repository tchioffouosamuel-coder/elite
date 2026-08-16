<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\School;
use App\Services\NumeroRecuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeroRecuTest extends TestCase
{
    use RefreshDatabase;

    private School $maternelle;

    private School $college;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maternelle = School::create(['name' => 'Elites Nursery', 'code' => 'EMA', 'type' => 'maternelle', 'is_active' => true]);
        $this->college = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->maternelle->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
    }

    private function service(): NumeroRecuService
    {
        return app(NumeroRecuService::class);
    }

    public function test_la_serie_est_continue_au_sein_d_une_ecole(): void
    {
        $numeros = collect(range(1, 3))->map(fn () => $this->service()->attribuer($this->maternelle, $this->annee->id));

        $this->assertSame(['RC-EMA-0001', 'RC-EMA-0002', 'RC-EMA-0003'], $numeros->all());
    }

    /** Le cœur de la demande : chaque école tient sa propre série. */
    public function test_chaque_ecole_a_sa_propre_serie(): void
    {
        $this->service()->attribuer($this->maternelle, $this->annee->id);
        $this->service()->attribuer($this->maternelle, $this->annee->id);

        $this->assertSame('RC-EBT-0001', $this->service()->attribuer($this->college, $this->annee->id));
        $this->assertSame('RC-EMA-0003', $this->service()->attribuer($this->maternelle, $this->annee->id));
    }

    public function test_la_serie_repart_a_un_a_chaque_annee_scolaire(): void
    {
        $this->service()->attribuer($this->maternelle, $this->annee->id);

        $suivante = AnneeScolaire::create([
            'school_id' => $this->maternelle->id, 'libelle' => '2027-2028',
            'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'is_active' => false,
        ]);

        $this->assertSame('RC-EMA-0001', $this->service()->attribuer($this->maternelle, $suivante->id));
    }

    public function test_le_format_se_surcharge_par_configuration(): void
    {
        config(['recu.prefixe' => 'REC', 'recu.longueur_numero' => 6]);

        $this->assertSame('REC-EMA-000001', $this->service()->attribuer($this->maternelle, $this->annee->id));
    }
}
