<?php

namespace Tests\Feature;

use App\Models\School;
use App\Services\InfrastructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bâti et mobilier de l'école — le rapport agrège les salles de classe et le
 * bloc administratif par matériau × état (tableau 18 MINEDUB), les autres
 * installations en quantité brute, et remonte le mobilier tel quel.
 */
class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EPP', 'type' => 'primaire', 'is_active' => true]);
    }

    private function service(): InfrastructureService
    {
        return app(InfrastructureService::class);
    }

    public function test_cree_et_modifie_une_infrastructure(): void
    {
        $infra = $this->service()->createInfrastructure($this->school->id, [
            'type' => 'salle_classe',
            'materiau' => 'dur',
            'etat' => 'bon',
            'quantite' => 20,
        ]);

        $this->assertSame('salle_classe', $infra->type);
        $this->assertSame(20, $infra->quantite);

        $infra = $this->service()->updateInfrastructure($infra, ['quantite' => 25, 'besoin_quantite' => 5]);

        $this->assertSame(25, $infra->fresh()->quantite);
        $this->assertSame(5, $infra->fresh()->besoin_quantite);
    }

    public function test_supprime_une_infrastructure(): void
    {
        $infra = $this->service()->createInfrastructure($this->school->id, [
            'type' => 'wc', 'quantite' => 14,
        ]);

        $this->service()->deleteInfrastructure($infra);

        $this->assertNull($infra::find($infra->id));
    }

    public function test_gere_le_mobilier_avec_ses_besoins(): void
    {
        $equipement = $this->service()->createEquipement($this->school->id, [
            'nature' => 'Tables-bancs', 'quantite' => 410, 'besoin_quantite' => 60,
        ]);

        $this->assertSame(410, $equipement->quantite);

        $equipement = $this->service()->updateEquipement($equipement, ['quantite' => 470]);
        $this->assertSame(470, $equipement->fresh()->quantite);

        $this->service()->deleteEquipement($equipement);
        $this->assertNull($equipement::find($equipement->id));
    }

    public function test_rapport_croise_les_salles_de_classe_par_materiau_et_etat(): void
    {
        $this->service()->createInfrastructure($this->school->id, ['type' => 'salle_classe', 'materiau' => 'dur', 'etat' => 'bon', 'quantite' => 28]);
        $this->service()->createInfrastructure($this->school->id, ['type' => 'bloc_administratif', 'materiau' => 'dur', 'etat' => 'bon', 'quantite' => 3]);
        $this->service()->createInfrastructure($this->school->id, ['type' => 'wc', 'quantite' => 14]);
        $this->service()->createInfrastructure($this->school->id, ['type' => 'cloture', 'quantite' => 1]);
        $this->service()->createInfrastructure($this->school->id, ['type' => 'logement_maitre', 'quantite' => 1]);
        $this->service()->createEquipement($this->school->id, ['nature' => 'Ordinateur', 'quantite' => 5, 'besoin_quantite' => 10]);

        $rapport = $this->service()->rapport($this->school->id);

        $this->assertSame(28, $rapport['salles_classe']['dur']['bon']);
        $this->assertSame(0, $rapport['salles_classe']['semi_dur']['bon']);
        $this->assertSame(3, $rapport['bloc_administratif']['dur']['bon']);
        $this->assertSame(14, $rapport['autres']['wc']);
        $this->assertSame(1, $rapport['autres']['cloture']);
        $this->assertSame(0, $rapport['autres']['point_eau']);
        $this->assertSame(1, $rapport['autres']['logement_maitre']);
        $this->assertCount(1, $rapport['equipements']);
        $this->assertSame('Ordinateur', $rapport['equipements']->first()->nature);
    }

    public function test_le_rapport_ne_deborde_pas_sur_une_autre_ecole(): void
    {
        $autreEcole = School::create(['name' => 'Autre', 'code' => 'AUT', 'type' => 'primaire', 'is_active' => true]);
        $this->service()->createInfrastructure($autreEcole->id, ['type' => 'wc', 'quantite' => 99]);
        $this->service()->createInfrastructure($this->school->id, ['type' => 'wc', 'quantite' => 14]);

        $rapport = $this->service()->rapport($this->school->id);

        $this->assertSame(14, $rapport['autres']['wc']);
    }
}
