<?php

namespace App\Services;

use App\Models\BudgetPersonnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Budgets alloués au personnel : suivi de ce qui a été donné, dépensé, et de
 * ce qu'il reste — sur le même principe que les avances sur salaire, mais
 * dont les mouvements sont directement les lignes de `depenses` (pas de
 * table de remboursements séparée, une allocation n'attend rien en retour).
 */
class BudgetPersonnelService extends BaseService
{
    /** @param int|array<int> $schoolId */
    public function lister(int|array $schoolId, ?int $personnelId = null): Collection
    {
        return BudgetPersonnel::forSchool($schoolId)
            ->when($personnelId, fn($q, $id) => $q->where('personnel_id', $id))
            ->with(['personnel', 'school:id,name,code,type', 'depenses'])
            ->orderByDesc('date_allocation')
            ->get();
    }

    /** @param int|array<int> $schoolId */
    public function trouver(int|array $schoolId, int $id): BudgetPersonnel
    {
        return BudgetPersonnel::forSchool($schoolId)
            ->with(['personnel', 'school:id,name,code,type', 'depenses.compte'])
            ->findOrFail($id);
    }

    /**
     * Budgets actifs (non clôturés) éligibles comme source d'une dépense — le
     * sélecteur du formulaire de dépense n'a rien à proposer d'épuisé ou clos.
     *
     * @param int|array<int> $schoolId
     */
    public function listerActifs(int|array $schoolId): Collection
    {
        return $this->lister($schoolId)->reject(fn(BudgetPersonnel $b) => $b->statut !== 'actif')->values();
    }

    /** @return array{effectif: int, total_alloue: int, total_depense: int, total_restant: int} */
    public function totaux(int|array $schoolId): array
    {
        $budgets = $this->lister($schoolId);

        return [
            'effectif' => $budgets->pluck('personnel_id')->unique()->count(),
            'total_alloue' => (int) $budgets->sum('montant_alloue'),
            'total_depense' => (int) $budgets->sum(fn(BudgetPersonnel $b) => $b->montant_depense),
            'total_restant' => (int) $budgets->sum(fn(BudgetPersonnel $b) => $b->solde),
        ];
    }

    /** @param array<string, mixed> $donnees */
    public function allouer(int $schoolId, array $donnees, ?int $allouePar = null): BudgetPersonnel
    {
        $budget = BudgetPersonnel::create([
            'school_id' => $schoolId,
            'personnel_id' => $donnees['personnel_id'],
            'annee_scolaire_id' => $donnees['annee_scolaire_id'] ?? null,
            'libelle' => $donnees['libelle'],
            'montant_alloue' => (int) $donnees['montant_alloue'],
            'date_allocation' => $donnees['date_allocation'] ?? Carbon::today()->toDateString(),
            'note_gestion' => $donnees['note_gestion'] ?? null,
            'alloue_par' => $allouePar,
        ]);

        return $budget->fresh(['personnel', 'school:id,name,code,type']);
    }

    /**
     * Modifie la note de gestion — le champ où le personnel explique comment
     * il compte utiliser son budget. Utilisé aussi bien par l'administration
     * que par l'intéressé lui-même depuis son espace.
     */
    public function modifierNoteGestion(BudgetPersonnel $budget, string $note): BudgetPersonnel
    {
        $budget->update(['note_gestion' => $note]);

        return $budget->fresh();
    }

    /**
     * Clôture le budget : il ne peut plus recevoir de nouvelles dépenses, mais
     * celles déjà imputées restent au registre — clôturer n'efface rien.
     */
    public function annuler(BudgetPersonnel $budget, string $motif, ?int $annulePar = null): BudgetPersonnel
    {
        if ($budget->estAnnule()) {
            throw new RuntimeException('Ce budget est déjà clôturé.');
        }

        $budget->update([
            'annule_le' => now(),
            'annule_par' => $annulePar,
            'motif_annulation' => $motif,
        ]);

        return $budget->fresh();
    }

    /**
     * Une dépense imputée sur un budget ne doit jamais faire passer son solde
     * sous zéro — c'est tout l'intérêt du suivi : au-delà, il faut repasser
     * par la caisse ou augmenter l'allocation, pas continuer à piocher dedans.
     *
     * @throws RuntimeException
     */
    public function verifierDisponibilite(BudgetPersonnel $budget, int $montant): void
    {
        if ($budget->estAnnule()) {
            throw new RuntimeException('Ce budget est clôturé, il ne peut plus recevoir de dépenses.');
        }

        if ($montant > $budget->solde) {
            throw new RuntimeException("Cette dépense dépasse le solde disponible du budget ({$budget->solde} F CFA restants).");
        }
    }

    /**
     * Bilan de gestion d'un budget sur une période : ce qui a été alloué,
     * dépensé, et ce qui reste — la question à laquelle répond le PDF remis au
     * personnel concerné et à sa hiérarchie.
     *
     * @return array{
     *   alloue: int, depense: int, solde: int, statut: string,
     *   depenses: Collection<int, \App\Models\Depense>,
     * }
     */
    public function bilan(BudgetPersonnel $budget, ?string $du = null, ?string $au = null): array
    {
        $depenses = $budget->depenses()
            ->when($du, fn($q, $d) => $q->whereDate('date_depense', '>=', $d))
            ->when($au, fn($q, $a) => $q->whereDate('date_depense', '<=', $a))
            ->with('compte')
            ->orderByDesc('date_depense')
            ->get();

        $retenu = (int) $depenses->where('statut', '!=', 'annulee')->sum('montant');

        return [
            'alloue' => $budget->montant_alloue,
            'depense' => $retenu,
            'solde' => max(0, $budget->montant_alloue - $retenu),
            'statut' => $budget->statut,
            'depenses' => $depenses,
        ];
    }
}
