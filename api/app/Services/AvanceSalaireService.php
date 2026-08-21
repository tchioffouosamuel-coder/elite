<?php

namespace App\Services;

use App\Models\AvanceSalaire;
use App\Models\Personnel;
use App\Models\Remuneration;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class AvanceSalaireService extends BaseService
{
    /** Part maximale du salaire brut qu'une mensualité de remboursement peut représenter. */
    private const PLAFOND_MENSUALITE = 0.5;


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

    public function calculerMensualite(int $montant, int $nombreMois): int
    {
        return (int) ceil($montant / $nombreMois);
    }

    /**
     * Salaire brut en cours et mensualité maximale qu'il autorise. `null`
     * quand l'employé n'a aucune rémunération enregistrée : l'interface le
     * signale plutôt que d'afficher un plafond de zéro, qui se lirait comme
     * un refus alors que c'est la fiche de paie qui manque.
     *
     * @return array{salaire_brut: int, plafond_mensualite: int}|null
     */
    public function plafond(Personnel $personnel): ?array
    {
        $remuneration = Remuneration::where('personnel_id', $personnel->id)
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->first();

        if (! $remuneration) {
            return null;
        }

        return [
            'salaire_brut' => (int) $remuneration->brut,
            'plafond_mensualite' => (int) floor($remuneration->brut * self::PLAFOND_MENSUALITE),
        ];
    }

    /**
     * Vérifie que la mensualité issue de (montant, nombre de mois) ne dépasse
     * pas 50% du salaire brut en cours de l'employé, et renvoie cette
     * mensualité — utilisé à la fois à l'octroi direct et à la soumission
     * d'une demande, pour ne jamais laisser passer un échéancier intenable.
     */
    public function verifierPlafond(Personnel $personnel, int $montant, int $nombreMois): int
    {
        $bornes = $this->plafond($personnel);

        if (! $bornes) {
            throw new RuntimeException("Aucune rémunération n'est renseignée pour {$personnel->nom_complet} : impossible de calculer le plafond de remboursement.");
        }

        $mensualite = $this->calculerMensualite($montant, $nombreMois);
        $plafond = $bornes['plafond_mensualite'];

        if ($mensualite > $plafond) {
            throw new RuntimeException(
                "La mensualité de remboursement ({$mensualite} F CFA) dépasse 50% du salaire brut ({$plafond} F CFA). Augmentez le nombre de mois ou réduisez le montant.",
            );
        }

        return $mensualite;
    }

    /** @param array{personnel_id: int, montant: int, nombre_mois: int, date_avance: string, motif?: ?string} $donnees */
    public function accorder(int $schoolId, array $donnees, ?int $accordeePar): AvanceSalaire
    {
        $personnel = Personnel::findOrFail($donnees['personnel_id']);
        $mensualite = $this->verifierPlafond($personnel, (int) $donnees['montant'], (int) $donnees['nombre_mois']);

        return AvanceSalaire::create([
            ...$donnees,
            'mensualite' => $mensualite,
            'school_id' => $schoolId,
            'accordee_par' => $accordeePar,
        ]);
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

    /**
     * Avances en cours de remboursement pour un agent, la plus ancienne
     * d'abord — c'est l'ordre dans lequel une retenue s'impute.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AvanceSalaire>
     */
    public function enCoursPour(int $personnelId): Collection
    {
        return AvanceSalaire::where('personnel_id', $personnelId)
            ->valides()
            ->with('remboursements')
            ->orderBy('date_avance')
            ->get()
            ->filter(fn (AvanceSalaire $a) => $a->solde > 0)
            ->values();
    }

    /**
     * Retenue du mois au titre des avances : la somme des mensualités de
     * l'échéancier, chacune bornée par ce qui reste dû.
     *
     * C'est ce montant que la paie propose en déduction — sans quoi
     * l'échéancier resterait une intention, recopiée à la main d'un mois sur
     * l'autre comme dans les registres.
     */
    public function mensualiteDue(int $personnelId): int
    {
        return (int) $this->enCoursPour($personnelId)
            ->sum(fn (AvanceSalaire $a) => min((int) ($a->mensualite ?? 0), $a->solde));
    }

    /**
     * Impute une retenue de paie sur les avances en cours, la plus ancienne
     * d'abord, et renvoie ce qui a réellement pu être imputé.
     *
     * Le montant retenu sur le bulletin et les remboursements enregistrés
     * doivent dire la même chose : si l'agent doit moins que la retenue
     * annoncée, seul le dû est imputé et l'écart remonte à l'appelant.
     */
    public function imputerSurPaie(int $personnelId, int $montant, string $date, ?string $note = null): int
    {
        if ($montant <= 0) {
            return 0;
        }

        return $this->transaction(function () use ($personnelId, $montant, $date, $note) {
            $restant = $montant;

            foreach ($this->enCoursPour($personnelId) as $avance) {
                if ($restant <= 0) {
                    break;
                }

                $part = min($restant, $avance->solde);

                $avance->remboursements()->create([
                    'montant' => $part,
                    'date_remboursement' => $date,
                    'mode' => 'retenue_salaire',
                    'note' => $note,
                ]);

                $restant -= $part;
            }

            return $montant - $restant;
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
