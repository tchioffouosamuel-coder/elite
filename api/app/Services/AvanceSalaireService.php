<?php

namespace App\Services;

use App\Models\AvanceSalaire;
use App\Models\Personnel;
use App\Models\Remuneration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
     * Nombre de mois qu'un remboursement à mensualité fixe prend pour
     * s'éteindre — la dernière mensualité peut être plus faible que les
     * autres, l'échéancier n'a pas à être uniforme.
     */
    public function calculerNombreMois(int $montant, int $mensualite): int
    {
        return (int) ceil($montant / $mensualite);
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
     * Vérifie qu'une mensualité choisie par l'employé ne dépasse pas 50% du
     * salaire brut en cours — utilisé à la fois à l'octroi direct et à la
     * soumission d'une demande, pour ne jamais laisser passer un échéancier
     * intenable. L'échéancier n'est plus supposé uniforme : c'est la
     * mensualité elle-même qui est saisie, pas un nombre de mois dont elle se
     * déduirait.
     */
    public function verifierPlafond(Personnel $personnel, int $montant, int $mensualite): int
    {
        $bornes = $this->plafond($personnel);

        if (! $bornes) {
            throw new RuntimeException("Aucune rémunération n'est renseignée pour {$personnel->nom_complet} : impossible de calculer le plafond de remboursement.");
        }

        $plafond = $bornes['plafond_mensualite'];

        if ($mensualite > $plafond) {
            throw new RuntimeException(
                "La mensualité de remboursement ({$mensualite} F CFA) dépasse 50% du salaire brut ({$plafond} F CFA). Réduisez la mensualité ou le montant.",
            );
        }

        return $mensualite;
    }

    /**
     * @param  array{personnel_id: int, montant: int, mensualite: int, date_avance: string, motif?: ?string, mois_debut_remboursement?: ?string}  $donnees
     */
    public function accorder(int $schoolId, array $donnees, ?int $accordeePar): AvanceSalaire
    {
        $personnel = Personnel::findOrFail($donnees['personnel_id']);
        $montant = (int) $donnees['montant'];
        $mensualite = $this->verifierPlafond($personnel, $montant, (int) $donnees['mensualite']);

        return AvanceSalaire::create([
            'school_id' => $schoolId,
            'personnel_id' => $donnees['personnel_id'],
            'montant' => $montant,
            'mensualite' => $mensualite,
            'nombre_mois' => $this->calculerNombreMois($montant, $mensualite),
            // Par défaut, la retenue commence dès le mois en cours — mais
            // l'employé (ou l'admin à l'octroi direct) peut la décaler.
            'mois_debut_remboursement' => $donnees['mois_debut_remboursement'] ?? now()->startOfMonth()->toDateString(),
            'date_avance' => $donnees['date_avance'],
            'motif' => $donnees['motif'] ?? null,
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
     * `$auTitreDe`, quand fourni, exclut les avances dont le mois de début
     * de remboursement est postérieur à cette période : une avance accordée
     * pour démarrer en mars n'entame pas la paie de janvier ou février.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AvanceSalaire>
     */
    public function enCoursPour(int $personnelId, ?string $auTitreDe = null): Collection
    {
        return AvanceSalaire::where('personnel_id', $personnelId)
            ->valides()
            ->with('remboursements')
            ->orderBy('date_avance')
            ->get()
            ->filter(fn (AvanceSalaire $a) => $a->solde > 0)
            ->filter(fn (AvanceSalaire $a) => $auTitreDe === null
                || $a->mois_debut_remboursement === null
                || $a->mois_debut_remboursement->format('Y-m') <= Carbon::parse($auTitreDe)->format('Y-m'))
            ->values();
    }

    /**
     * Retenue du mois au titre des avances : la somme des mensualités de
     * l'échéancier, chacune bornée par ce qui reste dû — pour les seules
     * avances déjà entrées dans leur période de remboursement.
     *
     * C'est ce montant que la paie propose en déduction — sans quoi
     * l'échéancier resterait une intention, recopiée à la main d'un mois sur
     * l'autre comme dans les registres.
     */
    public function mensualiteDue(int $personnelId, string $periode): int
    {
        return (int) $this->enCoursPour($personnelId, $periode)
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

            foreach ($this->enCoursPour($personnelId, $date) as $avance) {
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
