<?php

namespace App\Services;

use App\Models\AvanceSalaire;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class AvanceSalaireService extends BaseService
{
    /**
     * @param  int|array<int>  $schoolId
     * @param  array{personnel_id?: ?int, statut?: ?string}  $filtres
     */
    public function lister(int|array $schoolId, array $filtres = []): Collection
    {
        return AvanceSalaire::forSchool($schoolId)
            ->with(['personnel.school', 'remboursements'])
            ->when($filtres['personnel_id'] ?? null, fn ($q, $id) => $q->where('personnel_id', $id))
            ->orderByDesc('date_avance')
            ->get()
            ->when(
                $filtres['statut'] ?? null,
                fn (Collection $avances, string $statut) => $avances->filter(fn (AvanceSalaire $a) => $a->statut === $statut)->values(),
            );
    }

    /** @param int|array<int> $schoolId */
    public function trouver(int|array $schoolId, int $id): AvanceSalaire
    {
        return AvanceSalaire::forSchool($schoolId)->with(['personnel.school', 'remboursements'])->findOrFail($id);
    }

    /** @param array{personnel_id: int, montant: int, date_avance: string, motif?: ?string} $donnees */
    public function accorder(int $schoolId, array $donnees, ?int $accordeePar): AvanceSalaire
    {
        return AvanceSalaire::create([...$donnees, 'school_id' => $schoolId, 'accordee_par' => $accordeePar]);
    }

    /** @param array{montant: int, date_remboursement?: ?string, mode?: string, note?: ?string} $donnees */
    public function rembourser(AvanceSalaire $avance, array $donnees): AvanceSalaire
    {
        if ($avance->estAnnulee()) {
            throw new RuntimeException('Cette avance est annulée, elle ne peut plus être remboursée.');
        }

        $montant = (int) $donnees['montant'];

        if ($montant <= 0) {
            throw new RuntimeException('Le montant remboursé doit être supérieur à zéro.');
        }

        if ($montant > $avance->solde) {
            throw new RuntimeException("Le remboursement ({$montant} F) dépasse le solde restant ({$avance->solde} F).");
        }

        return $this->transaction(function () use ($avance, $donnees, $montant) {
            $avance->remboursements()->create([
                'montant' => $montant,
                'date_remboursement' => $donnees['date_remboursement'] ?? now()->toDateString(),
                'mode' => $donnees['mode'] ?? 'retenue_salaire',
                'note' => $donnees['note'] ?? null,
            ]);

            return $avance->fresh(['remboursements']);
        });
    }

    public function annuler(AvanceSalaire $avance, string $motif, ?int $annulePar): AvanceSalaire
    {
        if ($avance->estAnnulee()) {
            throw new RuntimeException('Cette avance est déjà annulée.');
        }

        if ($avance->montant_rembourse > 0) {
            throw new RuntimeException('Une avance déjà partiellement remboursée ne peut plus être annulée.');
        }

        $avance->update(['annule_le' => now(), 'annule_par' => $annulePar, 'motif_annulation' => $motif]);

        return $avance;
    }

    /**
     * @param  int|array<int>  $schoolId
     * @return array{effectif: int, total_accorde: int, total_rembourse: int, total_restant: int}
     */
    public function totaux(int|array $schoolId): array
    {
        $avances = AvanceSalaire::forSchool($schoolId)->valides()->with('remboursements')->get();

        return [
            'effectif' => $avances->pluck('personnel_id')->unique()->count(),
            'total_accorde' => (int) $avances->sum('montant'),
            'total_rembourse' => (int) $avances->sum(fn (AvanceSalaire $a) => $a->montant_rembourse),
            'total_restant' => (int) $avances->sum(fn (AvanceSalaire $a) => $a->solde),
        ];
    }
}
