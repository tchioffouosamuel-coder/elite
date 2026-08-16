<?php

namespace Tests\Feature;

use App\Services\Paie\BaremePaie;
use Tests\TestCase;

/**
 * Le barème engage l'établissement devant la CNPS et le fisc : chaque règle
 * est vérifiée sur un cas dont le résultat attendu est calculable à la main.
 *
 * Le cas de référence est repris du bulletin d'avril 2024 de l'établissement
 * (BOIGA DJANABOU) : 42 000 de base, 1 000 d'ancienneté, 2 500 de
 * communication et 2 500 de transport, soit 48 000 bruts.
 */
class BaremePaieTest extends TestCase
{
    private const CAS_REFERENCE = [
        'salaire_base' => 42000,
        'prime_anciennete' => 1000,
        'prime_communication' => 2500,
        'prime_transport' => 2500,
    ];

    private function calculer(array $gains = self::CAS_REFERENCE)
    {
        return (new BaremePaie)->calculer($gains);
    }

    private function retenue(string $prefixe, array $retenues): array
    {
        foreach ($retenues as $ligne) {
            if (str_starts_with($ligne['libelle'], $prefixe)) {
                return $ligne;
            }
        }

        $this->fail("Retenue « {$prefixe} » absente du barème.");
    }

    public function test_le_transport_et_la_communication_sortent_de_l_assiette(): void
    {
        $resultat = $this->calculer();

        $this->assertSame(48000, $resultat->brut);
        // 48 000 − 2 500 (transport) − 2 500 (communication) : la base que
        // portent déjà les bulletins de l'établissement.
        $this->assertSame(43000, $resultat->baseTaxable);
    }

    public function test_l_exoneration_est_plafonnee(): void
    {
        // Transport de 10 000 : seuls 2 500 sont exonérés, le reste est taxable.
        $resultat = $this->calculer(['salaire_base' => 42000, 'prime_transport' => 10000]);

        $this->assertSame(52000, $resultat->brut);
        $this->assertSame(49500, $resultat->baseTaxable);
    }

    public function test_les_cotisations_cnps_portent_sur_la_base_taxable(): void
    {
        $pension = $this->retenue('CNPS — Pension', $this->calculer()->retenues);

        // 4,2 % de 43 000, part salariale et part patronale.
        $this->assertSame(43000, $pension['base']);
        $this->assertSame(1806, $pension['montant_salarial']);
        $this->assertSame(1806, $pension['montant_patronal']);
    }

    public function test_les_prestations_familiales_et_l_accident_du_travail_sont_patronaux(): void
    {
        $retenues = $this->calculer()->retenues;

        $familiales = $this->retenue('CNPS — Prestations', $retenues);
        $this->assertSame(0, $familiales['montant_salarial']);
        $this->assertSame(3010, $familiales['montant_patronal']); // 7 % de 43 000

        $accidents = $this->retenue('CNPS — Accidents', $retenues);
        $this->assertSame(0, $accidents['montant_salarial']);
        $this->assertSame(753, $accidents['montant_patronal']); // 1,75 % de 43 000
    }

    public function test_l_assiette_cnps_est_plafonnee(): void
    {
        $resultat = $this->calculer(['salaire_base' => 1200000]);
        $pension = $this->retenue('CNPS — Pension', $resultat->retenues);

        // Au-delà de 750 000, l'assiette n'augmente plus.
        $this->assertSame(750000, $pension['base']);
        $this->assertSame(31500, $pension['montant_salarial']);
    }

    public function test_le_credit_foncier_a_deux_parts_et_le_fne_une_seule(): void
    {
        $retenues = $this->calculer()->retenues;

        $cfc = $this->retenue('Crédit Foncier', $retenues);
        $this->assertSame(430, $cfc['montant_salarial']);   // 1 % de 43 000
        $this->assertSame(645, $cfc['montant_patronal']);   // 1,5 % de 43 000

        $fne = $this->retenue('Fonds National', $retenues);
        $this->assertSame(0, $fne['montant_salarial']);
        $this->assertSame(430, $fne['montant_patronal']);   // 1 % de 43 000
    }

    /**
     * (43 000 × 12 × 70 %) − (1 806 × 12) − 500 000 = −120 672 : négatif, donc
     * pas d'impôt. Le bulletin d'avril 2024 affiche bien un IRPP nul.
     */
    public function test_un_petit_salaire_n_est_pas_imposable(): void
    {
        $irpp = $this->retenue('IRPP', $this->calculer()->retenues);

        $this->assertSame(0, $irpp['montant_salarial']);
    }

    /**
     * Base 400 000 : RNI = 400 000 × 12 × 0,7 − 16 800 × 12 − 500 000
     *                    = 3 360 000 − 201 600 − 500 000 = 2 658 400.
     * Impôt = 2 000 000 × 10 % + 658 400 × 15 % = 200 000 + 98 760 = 298 760,
     * plus 10 % de CAC = 328 636, soit 27 386 par mois.
     */
    public function test_le_bareme_progressif_franchit_les_tranches(): void
    {
        $resultat = $this->calculer(['salaire_base' => 400000]);
        $irpp = $this->retenue('IRPP', $resultat->retenues);

        $this->assertSame(400000, $resultat->baseTaxable);
        $this->assertSame(27386, $irpp['montant_salarial']);
    }

    /** Montant forfaitaire par tranche, et non un pourcentage. */
    public function test_la_taxe_de_developpement_local_suit_un_forfait(): void
    {
        $this->assertSame(0, $this->retenue('Taxe de dév', $this->calculer()->retenues)['montant_salarial']);

        foreach ([[70000, 250], [90000, 500], [140000, 1000], [400000, 2250], [900000, 2500]] as [$base, $attendu]) {
            $retenues = $this->calculer(['salaire_base' => $base])->retenues;
            $this->assertSame($attendu, $this->retenue('Taxe de dév', $retenues)['montant_salarial'], "base {$base}");
        }
    }

    public function test_les_totaux_recoupent_les_lignes(): void
    {
        $resultat = $this->calculer();

        $this->assertSame(
            array_sum(array_column($resultat->retenues, 'montant_salarial')),
            $resultat->chargesSalariales,
        );
        $this->assertSame($resultat->brut - $resultat->chargesSalariales, $resultat->netAvantDeductions());
        $this->assertSame($resultat->brut + $resultat->chargesPatronales, $resultat->coutEmployeur());
    }

    public function test_le_bareme_se_surcharge_par_configuration(): void
    {
        config(['paie.cnps.pension_salarie' => 5.0]);

        $pension = $this->retenue('CNPS — Pension', $this->calculer()->retenues);

        $this->assertSame(2150, $pension['montant_salarial']); // 5 % de 43 000
    }
}
