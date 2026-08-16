<?php

namespace App\Services\Paie;

/**
 * Résultat d'un calcul de paie, avant application des déductions propres à
 * l'établissement (raff, njangi, prêt, absences) qui, elles, ne relèvent
 * d'aucun barème et sont saisies bulletin par bulletin.
 */
readonly class ResultatPaie
{
    /**
     * @param  array<string, int>  $gains
     * @param  list<array{libelle: string, libelle_en: string, base: int, taux_salarial: ?float, taux_patronal: ?float, montant_salarial: int, montant_patronal: int}>  $retenues
     */
    public function __construct(
        public int $brut,
        public int $baseTaxable,
        public int $chargesSalariales,
        public int $chargesPatronales,
        public array $gains,
        public array $retenues,
    ) {}

    /** Net avant les déductions maison. */
    public function netAvantDeductions(): int
    {
        return $this->brut - $this->chargesSalariales;
    }

    /** Ce que l'agent coûte réellement, part patronale comprise. */
    public function coutEmployeur(): int
    {
        return $this->brut + $this->chargesPatronales;
    }

    /** @return array<string, int|array> */
    public function toArray(): array
    {
        return [
            'brut' => $this->brut,
            'base_taxable' => $this->baseTaxable,
            'charges_salariales' => $this->chargesSalariales,
            'charges_patronales' => $this->chargesPatronales,
            'net_avant_deductions' => $this->netAvantDeductions(),
            'cout_employeur' => $this->coutEmployeur(),
            'gains' => $this->gains,
            'retenues' => $this->retenues,
        ];
    }
}
