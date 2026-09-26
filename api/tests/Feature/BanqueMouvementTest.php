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
