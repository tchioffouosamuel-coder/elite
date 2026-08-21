<?php

namespace App\Services\Paie;

/**
 * Barème effectivement pratiqué par l'établissement dans ses registres de paie
 * et ses états de cotisations.
 *
 * Il diffère du barème légal sur trois points, relevés sur les feuilles de
 * mai 2026 et l'état de cotisations d'avril 2024 :
 *
 *  1. **Taux forfaitaires.** La taxe de développement local s'applique à
 *     0,38 % — un pourcentage, là où la loi retient un montant par tranche.
 *     Aucun IRPP n'est prélevé, les assiettes déclarées restant sous le seuil
 *     d'imposition. Le total ressort à 5,58 % de retenue salariale et 15,45 %
 *     de contribution patronale, soit 21,03 % de l'assiette.
 *
 *  2. **Assiette plafonnée.** La colonne « salaire de base » des registres
 *     s'arrête à 60 000 F, le surplus étant reclassé en « autres avantages »,
 *     et c'est cette base plafonnée qui sert de référence à la déclaration.
 *     Les registres, eux, calculent parfois sur le total : deux assiettes
 *     coexistaient donc pour un même agent. On n'en retient qu'une — celle de
 *     la déclaration, qui commande les sommes réellement reversées.
 *
 *  3. **Charges salariales supportées par l'employeur.** L'agent perçoit le
 *     montant négocié à la rentrée, entier ; la part salariale ne se déduit
 *     pas de ce qu'il touche, l'école l'absorbe. Le coût employeur est donc le
 *     brut augmenté des deux parts, et non de la seule part patronale.
 *
 * ATTENTION : plafonner l'assiette déclarée est une décision qui engage
 * l'établissement — la pension de vieillesse se liquide sur les salaires
 * déclarés, et l'écart se répercute sur les droits de l'agent. Le plafond est
 * réglable (`PAIE_MAISON_PLAFOND_ASSIETTE`) et se neutralise en le portant à
 * zéro, auquel cas l'assiette suit le salaire réel.
 */
class BaremeMaison implements Bareme
{
    public function libelle(): string
    {
        return 'Barème maison — taux forfaitaires sur assiette plafonnée';
    }

    /** @param array<string, int> $gains */
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
        $assiette = $this->assiette($brut);

        $lignes = [
            $this->ligne('Taxe de développement local', 'Local development tax', $assiette, 'tdl_salarie', null),
            $this->ligne('Crédit Foncier du Cameroun', 'Housing fund', $assiette, 'cfc_salarie', 'cfc_employeur'),
            $this->ligne("Fonds National de l'Emploi", 'National employment fund', $assiette, null, 'fne_employeur'),
            $this->ligne('CNPS — Pension vieillesse', 'Old-age pension', $assiette, 'cnps_pension_salarie', 'cnps_pension_employeur'),
            $this->ligne('CNPS — Prestations familiales', 'Family benefits', $assiette, null, 'cnps_prestations_familiales'),
            $this->ligne('CNPS — Accidents du travail', 'Work injury', $assiette, null, 'cnps_accidents_travail'),
        ];

        return new ResultatPaie(
            brut: $brut,
            baseTaxable: $assiette,
            chargesSalariales: array_sum(array_column($lignes, 'montant_salarial')),
            chargesPatronales: array_sum(array_column($lignes, 'montant_patronal')),
            gains: $gains,
            retenues: $lignes,
            // Le net de l'agent ne bouge pas : c'est le montant négocié.
            chargesSalarialesSupporteesParEmployeur: (bool) config('paie.maison.charges_salariales_supportees_par_employeur'),
        );
    }

    /**
     * Assiette déclarée. Plafond à zéro : l'assiette suit le salaire réel,
     * ce qui est le comportement à retenir si l'établissement régularise.
     */
    private function assiette(int $brut): int
    {
        $plafond = (int) config('paie.maison.plafond_assiette');

        return $plafond > 0 ? min($brut, $plafond) : $brut;
    }

    /**
     * @return array{libelle: string, libelle_en: string, base: int, taux_salarial: ?float, taux_patronal: ?float, montant_salarial: int, montant_patronal: int}
     */
    private function ligne(string $libelle, string $libelleEn, int $base, ?string $cleSalarie, ?string $clePatronal): array
    {
        $tauxSalarial = $cleSalarie ? (float) config("paie.maison.taux.{$cleSalarie}") : 0.0;
        $tauxPatronal = $clePatronal ? (float) config("paie.maison.taux.{$clePatronal}") : 0.0;

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

    private function pourcentage(int $base, float $taux): int
    {
        return $taux > 0 ? (int) round($base * $taux / 100) : 0;
    }
}
