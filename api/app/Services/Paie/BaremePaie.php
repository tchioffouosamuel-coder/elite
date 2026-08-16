<?php

namespace App\Services\Paie;

/**
 * Application du barème légal à une rémunération mensuelle.
 *
 * Pure : aucune écriture en base, aucune dépendance à un modèle. Un bulletin
 * arrête ensuite le résultat ligne à ligne (cf. `bulletin_paie_lignes`), parce
 * qu'un barème change et qu'un bulletin déjà remis ne doit pas changer avec
 * lui. Cette classe se teste donc seule, ce qui compte pour un calcul dont
 * l'établissement répond devant la CNPS et le fisc.
 *
 * Tous les montants sont en francs CFA, arrondis au franc : le franc n'a pas
 * de subdivision et la CNPS attend des entiers.
 */
class BaremePaie
{
    /**
     * @param  array<string, int>  $gains  salaire_base, prime_anciennete,
     *                                     prime_communication, prime_transport,
     *                                     prime_recherche, prime_performance
     */
    public function calculer(array $gains): ResultatPaie
    {
        $gains = array_map(static fn ($v) => max(0, (int) $v), $gains + [
            'salaire_base' => 0,
            'prime_anciennete' => 0,
            'prime_communication' => 0,
            'prime_transport' => 0,
            'prime_recherche' => 0,
            'prime_performance' => 0,
        ]);

        $brut = array_sum($gains);
        $baseTaxable = $this->baseTaxable($brut, $gains);
        $assietteCnps = min($baseTaxable, (int) config('paie.cnps.plafond_mensuel'));

        $pensionSalarie = $this->pourcentage($assietteCnps, config('paie.cnps.pension_salarie'));

        $lignes = [
            $this->ligneImpot('IRPP', 'Income tax', $baseTaxable, $this->irpp($baseTaxable, $pensionSalarie), null, 0),
            $this->ligneImpot('Taxe de développement local', 'Local development tax', $gains['salaire_base'], $this->tdl($gains['salaire_base']), null, 0),
            $this->ligne(
                'Crédit Foncier du Cameroun', 'Housing fund', $baseTaxable,
                config('paie.cfc.salarie'), config('paie.cfc.employeur'),
            ),
            $this->ligne(
                "Fonds National de l'Emploi", 'National employment fund', $baseTaxable,
                0.0, config('paie.fne.employeur'),
            ),
            $this->ligne(
                'CNPS — Pension vieillesse', 'Old-age pension', $assietteCnps,
                config('paie.cnps.pension_salarie'), config('paie.cnps.pension_employeur'),
            ),
            $this->ligne(
                'CNPS — Prestations familiales', 'Family benefits', $assietteCnps,
                0.0, config('paie.cnps.prestations_familiales'),
            ),
            $this->ligne(
                'CNPS — Accidents du travail', 'Work injury', $assietteCnps,
                0.0, config('paie.cnps.accidents_travail'),
            ),
        ];

        return new ResultatPaie(
            brut: $brut,
            baseTaxable: $baseTaxable,
            chargesSalariales: array_sum(array_column($lignes, 'montant_salarial')),
            chargesPatronales: array_sum(array_column($lignes, 'montant_patronal')),
            gains: $gains,
            retenues: $lignes,
        );
    }

    /**
     * Transport et communication sont exonérés dans la limite de leur plafond :
     * ils remboursent un frais. Au-delà du plafond, l'excédent redevient du
     * salaire et rentre dans l'assiette.
     *
     * @param  array<string, int>  $gains
     */
    private function baseTaxable(int $brut, array $gains): int
    {
        $exonere = 0;

        foreach (['transport' => 'prime_transport', 'communication' => 'prime_communication'] as $cle => $champ) {
            $exonere += min($gains[$champ], (int) config("paie.exonerations.{$cle}"));
        }

        return max(0, $brut - $exonere);
    }

    /**
     * Barème progressif appliqué au revenu net imposable annuel, puis ramené
     * au mois. Les centimes additionnels communaux s'ajoutent à l'impôt.
     */
    private function irpp(int $baseTaxable, int $pensionSalarieMensuelle): int
    {
        $revenuNetImposable = (int) round(
            $baseTaxable * 12 * (1 - config('paie.irpp.taux_frais_professionnels') / 100)
            - $pensionSalarieMensuelle * 12
            - config('paie.irpp.abattement_annuel')
        );

        if ($revenuNetImposable <= 0) {
            return 0;
        }

        $impot = 0.0;
        $plancher = 0;

        foreach (config('paie.irpp.tranches') as [$plafond, $taux]) {
            $tranche = $plafond === null
                ? $revenuNetImposable - $plancher
                : min($revenuNetImposable, $plafond) - $plancher;

            if ($tranche <= 0) {
                break;
            }

            $impot += $tranche * $taux / 100;
            $plancher = (int) $plafond;

            if ($plafond !== null && $revenuNetImposable <= $plafond) {
                break;
            }
        }

        $impot *= 1 + config('paie.irpp.centimes_additionnels') / 100;

        return (int) round($impot / 12);
    }

    /** Montant forfaitaire par tranche de salaire de base, et non un pourcentage. */
    private function tdl(int $salaireBase): int
    {
        foreach (config('paie.tdl.tranches') as [$plafond, $montant]) {
            if ($plafond === null || $salaireBase <= $plafond) {
                return (int) $montant;
            }
        }

        return 0;
    }

    /**
     * @return array{libelle: string, libelle_en: string, base: int, taux_salarial: ?float, taux_patronal: ?float, montant_salarial: int, montant_patronal: int}
     */
    private function ligne(string $libelle, string $libelleEn, int $base, float $tauxSalarial, float $tauxPatronal): array
    {
        return [
            'libelle' => $libelle,
            'libelle_en' => $libelleEn,
            'base' => $base,
            'taux_salarial' => $tauxSalarial > 0 ? $tauxSalarial : null,
            'taux_patronal' => $tauxPatronal > 0 ? $tauxPatronal : null,
            'montant_salarial' => $this->pourcentage($base, $tauxSalarial),
            'montant_patronal' => $this->pourcentage($base, $tauxPatronal),
        ];
    }

    /** IRPP et TDL ne se déduisent pas d'un taux : leur montant est calculé à part. */
    private function ligneImpot(string $libelle, string $libelleEn, int $base, int $montant, ?float $taux, int $patronal): array
    {
        return [
            'libelle' => $libelle,
            'libelle_en' => $libelleEn,
            'base' => $base,
            'taux_salarial' => $taux,
            'taux_patronal' => null,
            'montant_salarial' => $montant,
            'montant_patronal' => $patronal,
        ];
    }

    private function pourcentage(int $base, float $taux): int
    {
        return $taux > 0 ? (int) round($base * $taux / 100) : 0;
    }
}
