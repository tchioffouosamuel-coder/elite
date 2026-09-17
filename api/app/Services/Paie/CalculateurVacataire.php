<?php

namespace App\Services\Paie;

use App\Models\Setting;

/**
 * Calcul du bulletin d'un vacataire — un régime à part de {@see BaremePaie}
 * et {@see BaremeMaison} : un vacataire n'a ni salaire de base ni primes, son
 * brut est heures × taux plus un éventuel complément ponctuel du mois, et il
 * ne relève de la CNPS que si son contrat le déclare ({@see
 * \App\Models\Remuneration::$cnps_actif}) — contrairement au salarié mensuel,
 * qui y est de droit. L'impôt, lui, s'applique dès que la CNPS est active :
 * ce n'est pas un choix, mais il n'a pas de raison d'être si l'agent n'est
 * même pas déclaré.
 *
 * Tous les taux sont des réglages par établissement (App\Services\SettingsCatalog,
 * groupe `paie_vacataires`), pas des constantes : aucune valeur n'existait
 * avant l'ouverture de ce régime, à l'établissement de les fixer lui-même.
 * Par défaut ils valent 0 — sans effet tant qu'un super admin ne les a pas
 * renseignés.
 */
class CalculateurVacataire
{
    public function calculer(int $brut, bool $cnpsActif, int $schoolId): ResultatPaie
    {
        $lignes = [];

        if ($cnpsActif) {
            $lignes[] = $this->ligne(
                'CNPS — Pension vieillesse', 'Old-age pension', $brut, $schoolId,
                'paie_vacataire_cnps_pension_salarie', 'paie_vacataire_cnps_pension_employeur',
            );
            $lignes[] = $this->ligne(
                'CNPS — Prestations familiales', 'Family benefits', $brut, $schoolId,
                null, 'paie_vacataire_cnps_prestations_familiales',
            );
            $lignes[] = $this->ligne(
                'CNPS — Accidents du travail', 'Work injury', $brut, $schoolId,
                null, 'paie_vacataire_cnps_accidents_travail',
            );
            $lignes[] = $this->ligne(
                'Impôt obligatoire', 'Mandatory tax', $brut, $schoolId,
                'paie_vacataire_impot_salarie', 'paie_vacataire_impot_employeur',
            );
        }

        return new ResultatPaie(
            brut: $brut,
            baseTaxable: $brut,
            chargesSalariales: array_sum(array_column($lignes, 'montant_salarial')),
            chargesPatronales: array_sum(array_column($lignes, 'montant_patronal')),
            gains: [],
            retenues: $lignes,
        );
    }

    /**
     * @return array{libelle: string, libelle_en: string, base: int, taux_salarial: ?float, taux_patronal: ?float, montant_salarial: int, montant_patronal: int}
     */
    private function ligne(string $libelle, string $libelleEn, int $base, int $schoolId, ?string $cleSalarie, ?string $clePatronal): array
    {
        $tauxSalarial = $cleSalarie ? (float) Setting::get($schoolId, $cleSalarie, 0) : 0.0;
        $tauxPatronal = $clePatronal ? (float) Setting::get($schoolId, $clePatronal, 0) : 0.0;

        return [
            'libelle' => $libelle,
            'libelle_en' => $libelleEn,
            'base' => $base,
            'taux_salarial' => $tauxSalarial > 0 ? $tauxSalarial : null,
            'taux_patronal' => $tauxPatronal > 0 ? $tauxPatronal : null,
            'montant_salarial' => $tauxSalarial > 0 ? (int) round($base * $tauxSalarial / 100) : 0,
            'montant_patronal' => $tauxPatronal > 0 ? (int) round($base * $tauxPatronal / 100) : 0,
        ];
    }
}
