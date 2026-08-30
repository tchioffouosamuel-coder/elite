<?php

namespace App\Services;

use App\Models\BudgetFonctionnement;
use App\Models\Depense;

class BudgetFonctionnementService
{
    private const RUBRIQUES = ['primes_rendement', 'projet_ecole', 'fenassco', 'fonctionnement', 'evaluation'];

    /**
     * Perçu/dépensé/reste par rubrique (tableau 21 MINEDUB) — le perçu vient
     * des lignes saisies manuellement, le dépensé se recalcule à chaque
     * appel depuis les dépenses taguées sur la rubrique : les deux ne
     * peuvent donc jamais diverger.
     *
     * @param  int|array<int>  $schoolId
     * @return list<array{rubrique: string, montant_percu: int, montant_depense: int, reste: int}>
     */
    public function rapport(int|array $schoolId, int $anneeScolaireId): array
    {
        $percus = BudgetFonctionnement::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->pluck('montant_percu', 'rubrique');

        $depenses = Depense::query()
            ->forSchool($schoolId)
            ->valides()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereNotNull('rubrique_budget_fonctionnement')
            ->selectRaw('rubrique_budget_fonctionnement as rubrique, SUM(montant) as total')
            ->groupBy('rubrique_budget_fonctionnement')
            ->pluck('total', 'rubrique');

        return collect(self::RUBRIQUES)->map(function (string $rubrique) use ($percus, $depenses) {
            $percu = (int) ($percus[$rubrique] ?? 0);
            $depense = (int) ($depenses[$rubrique] ?? 0);

            return [
                'rubrique' => $rubrique,
                'montant_percu' => $percu,
                'montant_depense' => $depense,
                'reste' => $percu - $depense,
            ];
        })->all();
    }

    public function definirMontantPercu(int $schoolId, int $anneeScolaireId, string $rubrique, int $montantPercu, ?string $observations = null): BudgetFonctionnement
    {
        return BudgetFonctionnement::updateOrCreate(
            ['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId, 'rubrique' => $rubrique],
            ['montant_percu' => $montantPercu, 'observations' => $observations],
        );
    }
}
