<?php

namespace Tests\Feature;

use App\Models\Banque;
use App\Services\BanqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BanqueMouvementTest extends TestCase
{
    use RefreshDatabase;

    public function test_depots_et_retraits_manuels_actualisent_le_solde_et_conservent_un_historique(): void
    {
        $banque = Banque::create(['nom' => 'Banque de test']);
        $service = app(BanqueService::class);

        $service->deposer($banque->id, 100000, '2026-09-01', 'Solde initial', 'DEP-001', null);
        $service->retirer($banque->id, 25000, '2026-09-02', 'Retrait caisse', 'RET-001', null);

        $this->assertSame(75000, $banque->fresh()->solde);
        $this->assertSame(2, $banque->mouvements()->count());
        $this->assertSame('depot', $banque->mouvements()->oldest('id')->firstOrFail()->type);
        $this->assertSame('retrait', $banque->mouvements()->latest('id')->firstOrFail()->type);
    }

    public function test_modifier_un_depot_reajuste_le_solde_de_lecart(): void
    {
        $banque = Banque::create(['nom' => 'Banque modifiable']);
        $service = app(BanqueService::class);

        $depot = $service->deposer($banque->id, 100000, '2026-09-01', 'Dépôt', 'DEP-001', null);
        $service->retirer($banque->id, 30000, '2026-09-02', 'Retrait', null, null);

        $service->modifierDepot($banque->id, $depot->id, 120000, '2026-09-03', 'Dépôt corrigé', 'DEP-002');

        $this->assertSame(90000, $banque->fresh()->solde);
        $depot->refresh();
        $this->assertSame(120000, $depot->montant);
        $this->assertSame('2026-09-03', $depot->date_mouvement->toDateString());
        $this->assertSame('Dépôt corrigé', $depot->libelle);
        $this->assertSame('DEP-002', $depot->reference);
    }

    public function test_baisser_un_depot_sous_ce_qui_a_deja_ete_retire_est_refuse(): void
    {
        $banque = Banque::create(['nom' => 'Banque entamée']);
        $service = app(BanqueService::class);

        $depot = $service->deposer($banque->id, 100000, null, 'Dépôt', null, null);
        $service->retirer($banque->id, 80000, null, 'Retrait', null, null);

        try {
            $service->modifierDepot($banque->id, $depot->id, 10000, null, 'Dépôt', null);
            $this->fail('La baisse aurait dû être refusée.');
        } catch (ValidationException) {
            $this->assertSame(20000, $banque->fresh()->solde);
            $this->assertSame(100000, $depot->fresh()->montant);
        }
    }

    public function test_un_retrait_ne_peut_pas_etre_modifie(): void
    {
        $banque = Banque::create(['nom' => 'Banque retrait']);
        $service = app(BanqueService::class);
        $service->deposer($banque->id, 50000, null, 'Dépôt', null, null);
        $retrait = $service->retirer($banque->id, 10000, null, 'Retrait', null, null);

        $this->expectException(ValidationException::class);
        $service->modifierDepot($banque->id, $retrait->id, 5000, null, 'Retrait', null);
    }

    public function test_un_retrait_superieur_au_solde_est_refuse_sans_creer_de_mouvement(): void
    {
        $banque = Banque::create(['nom' => 'Banque sans provision']);

        try {
            app(BanqueService::class)->retirer($banque->id, 1, null, 'Retrait', null, null);
            $this->fail('Le retrait aurait dû être refusé.');
        } catch (ValidationException) {
            $this->assertSame(0, $banque->fresh()->solde);
            $this->assertSame(0, $banque->mouvements()->count());
        }
    }
}
