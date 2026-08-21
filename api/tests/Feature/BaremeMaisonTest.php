<?php

namespace Tests\Feature;

use App\Services\Paie\Bareme;
use App\Services\Paie\BaremeMaison;
use App\Services\Paie\BaremePaie;
use Tests\TestCase;

/**
 * Barème effectivement pratiqué dans les registres de l'établissement.
 *
 * Les montants de référence viennent de l'état de cotisations d'avril 2024 :
 * une assiette de 60 000 F y donne 2 520 F de pension salariale, 828 F
 * d'impôts salariaux et 7 770 F de contribution patronale.
 */
class BaremeMaisonTest extends TestCase
{
    private function bareme(): BaremeMaison
    {
        return new BaremeMaison;
    }

    /** @param array<string, int> $gains */
    private function calculer(int $base, array $gains = [])
    {
        return $this->bareme()->calculer(['salaire_base' => $base] + $gains);
    }

    public function test_la_retenue_salariale_vaut_5_58_pourcent(): void
    {
        $resultat = $this->calculer(60000);

        // 0,38 % + 1 % + 4,2 % = 5,58 % → 3 348 F.
        $this->assertSame(3348, $resultat->chargesSalariales);
    }

    public function test_la_contribution_patronale_vaut_15_45_pourcent(): void
    {
        // 1,5 % + 1 % + 4,2 % + 7 % + 1,75 % = 15,45 % → 9 270 F.
        $this->assertSame(9270, $this->calculer(60000)->chargesPatronales);
    }

    public function test_le_prelevement_total_vaut_21_03_pourcent_de_l_assiette(): void
    {
        $resultat = $this->calculer(60000);

        $this->assertSame(12618, $resultat->chargesSalariales + $resultat->chargesPatronales);
    }

    public function test_la_pension_vieillesse_reprend_le_montant_de_l_etat_de_cotisations(): void
    {
        $pension = collect($this->calculer(60000)->retenues)
            ->firstWhere('libelle', 'CNPS — Pension vieillesse');

        $this->assertSame(2520, $pension['montant_salarial']);
        $this->assertSame(2520, $pension['montant_patronal']);
    }

    public function test_aucun_irpp_n_est_preleve(): void
    {
        $libelles = array_column($this->calculer(300000)->retenues, 'libelle');

        $this->assertNotContains('IRPP', $libelles);
    }

    public function test_l_assiette_est_plafonnee_a_60000(): void
    {
        $resultat = $this->calculer(100000);

        $this->assertSame(100000, $resultat->brut);
        $this->assertSame(60000, $resultat->baseTaxable);
        // Les cotisations suivent l'assiette déclarée, pas le salaire réel.
        $this->assertSame(3348, $resultat->chargesSalariales);
    }

    public function test_un_plafond_a_zero_fait_suivre_le_salaire_reel(): void
    {
        config(['paie.maison.plafond_assiette' => 0]);

        $resultat = $this->calculer(100000);

        $this->assertSame(100000, $resultat->baseTaxable);
        $this->assertSame(5580, $resultat->chargesSalariales);
    }

    public function test_les_primes_entrent_dans_le_brut_sans_exoneration(): void
    {
        // Le barème légal exonère transport et communication ; celui de la
        // maison ne connaît pas cette distinction.
        $resultat = $this->calculer(50000, ['prime_transport' => 2500, 'prime_communication' => 2500]);

        $this->assertSame(55000, $resultat->brut);
        $this->assertSame(55000, $resultat->baseTaxable);
    }

    // -------------------------------------------------- qui supporte la retenue

    public function test_l_agent_percoit_son_montant_negocie_entier(): void
    {
        $resultat = $this->calculer(60000);

        $this->assertTrue($resultat->chargesSalarialesSupporteesParEmployeur);
        $this->assertSame(60000, $resultat->netAvantDeductions());
    }

    public function test_le_cout_employeur_porte_les_deux_parts(): void
    {
        // 60 000 + 3 348 de part salariale absorbée + 9 270 de part patronale.
        $this->assertSame(72618, $this->calculer(60000)->coutEmployeur());
    }

    public function test_quand_l_ecole_ne_supporte_plus_la_part_salariale_le_net_baisse(): void
    {
        config(['paie.maison.charges_salariales_supportees_par_employeur' => false]);

        $resultat = $this->calculer(60000);

        $this->assertSame(56652, $resultat->netAvantDeductions());
        $this->assertSame(69270, $resultat->coutEmployeur());
    }

    // ------------------------------------------------------------- le réglage

    public function test_la_configuration_choisit_le_bareme_applique(): void
    {
        config(['paie.bareme' => 'legal']);
        $this->assertInstanceOf(BaremePaie::class, $this->app->make(Bareme::class));

        config(['paie.bareme' => 'maison']);
        $this->assertInstanceOf(BaremeMaison::class, $this->app->make(Bareme::class));
    }
}
