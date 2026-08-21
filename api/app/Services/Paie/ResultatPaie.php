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
        /**
         * L'établissement prend-il la part salariale à sa charge ? Dans les
         * registres de l'école, oui : l'agent perçoit le montant négocie à la
         * rentrée sans que taxes et CNPS ne l'amputent. Cela ne change pas ce
         * qui est dû aux organismes, seulement qui le supporte.
         */
        public bool $chargesSalarialesSupporteesParEmployeur = false,
    ) {}

    /** Net avant les déductions maison. */
    public function netAvantDeductions(): int
    {
        return $this->chargesSalarialesSupporteesParEmployeur
            ? $this->brut
            : $this->brut - $this->chargesSalariales;
    }

    /** Ce que l'agent coûte réellement, part patronale comprise. */
    public function coutEmployeur(): int
    {
        return $this->brut + $this->chargesPatronales
            + ($this->chargesSalarialesSupporteesParEmployeur ? $this->chargesSalariales : 0);
    }

    /** @return array<string, int|array> */
    public function toArray(): array
    {
        return [
            'brut' => $this->brut,
            'base_taxable' => $this->baseTaxable,
            'charges_salariales' => $this->chargesSalariales,
            'charges_patronales' => $this->chargesPatronales,
            'charges_salariales_supportees_par_employeur' => $this->chargesSalarialesSupporteesParEmployeur,
            'net_avant_deductions' => $this->netAvantDeductions(),
            'cout_employeur' => $this->coutEmployeur(),
            'gains' => $this->gains,
            'retenues' => $this->retenues,
        ];
    }
}
