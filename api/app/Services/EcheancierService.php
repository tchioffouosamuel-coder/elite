<?php

namespace App\Services;

use App\Models\DossierScolarite;
use App\Models\Setting;
use App\Models\TrancheScolarite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Échéancier de scolarité d'un dossier : ce qui est dû, quand, et ce qui reste
 * à régler sur chaque tranche.
 *
 * Deux usages, et c'est ce qui justifie de le centraliser ici :
 *
 * - le **portail parent** y lit les échéances à venir et celles en retard ;
 * - la **liste des insolvables** y lit ce qui est exigible AUJOURD'HUI, au lieu
 *   de comparer au total de l'année. Une famille à jour de sa première tranche
 *   n'a plus à figurer à côté de celle qui n'a rien versé.
 *
 * L'échéancier ne porte que la scolarité. Les frais annexes, le transport et le
 * reliquat de l'an passé restent exigibles à part — les découper les rendrait
 * dépendants d'un calendrier qui ne les concerne pas, et un frais ajouté en
 * cours d'année recalculerait tout le calendrier de la famille.
 *
 * Un établissement sans tranche garde le comportement antérieur : tout est
 * exigible immédiatement.
 */
class EcheancierService extends BaseService
{
    /** Statuts d'une tranche, du plus favorable au plus préoccupant. */
    public const STATUT_SOLDEE = 'soldee';

    public const STATUT_PARTIELLE = 'partielle';

    public const STATUT_A_VENIR = 'a_venir';

    public const STATUT_EN_RETARD = 'en_retard';

    /**
     * Tranches définies pour l'école et l'année, dans l'ordre.
     *
     * @return Collection<int, TrancheScolarite>
     */
    public function tranches(int $schoolId, int $anneeScolaireId): Collection
    {
        return TrancheScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('ordre')
            ->get();
    }

    /**
     * Échéancier d'un dossier, tranche par tranche.
     *
     * Les versements s'imputent dans l'ordre des échéances, du plus ancien au
     * plus récent : une famille qui verse une somme la voit d'abord solder ce
     * qu'elle devait déjà, ce qui est la lecture qu'en fait le comptoir.
     *
     * @return array{
     *     actif: bool,
     *     total_du: int,
     *     total_paye: int,
     *     du_a_ce_jour: int,
     *     retard: int,
     *     delai_grace: int,
     *     prochaine_echeance: ?array<string, mixed>,
     *     tranches: list<array<string, mixed>>
     * }
     */
    public function pourDossier(DossierScolarite $dossier, ?CarbonImmutable $aujourdHui = null): array
    {
        $aujourdHui ??= CarbonImmutable::today();

        // Seule la scolarité se découpe : on lit son poste dans la ventilation
        // du dossier, qui sait déjà ce qui a été réglé sur chacun.
        $poste = collect($dossier->rubriques)->firstWhere('cle', 'scolarite');
        $totalDu = (int) ($poste['montant_du'] ?? 0);
        $totalPaye = (int) ($poste['montant_paye'] ?? 0);

        $tranches = $this->tranches((int) $dossier->school_id, (int) $dossier->annee_scolaire_id);

        if ($tranches->isEmpty() || $totalDu <= 0) {
            // Sans échéancier, tout est exigible tout de suite — comportement
            // antérieur, que l'appelant doit pouvoir distinguer d'un retard.
            return [
                'actif' => false,
                'total_du' => $totalDu,
                'total_paye' => $totalPaye,
                'du_a_ce_jour' => $totalDu,
                'retard' => max(0, $totalDu - $totalPaye),
                'delai_grace' => 0,
                'prochaine_echeance' => null,
                'tranches' => [],
            ];
        }

        $delaiGrace = $this->delaiGrace((int) $dossier->school_id);
        $limite = $aujourdHui->subDays($delaiGrace);

        $montants = $this->repartir($totalDu, $tranches);
        $restantAImputer = $totalPaye;

        $lignes = [];
        $duACeJour = 0;

        foreach ($tranches as $index => $tranche) {
            $montant = $montants[$index];
            $couvert = min($montant, $restantAImputer);
            $restantAImputer -= $couvert;
            $reste = $montant - $couvert;

            // Le délai de grâce ne décale pas la date affichée à la famille :
            // il ne joue que sur le moment où l'impayé devient un retard.
            $exigible = $tranche->estEchue($limite);

            if ($exigible) {
                $duACeJour += $montant;
            }

            $lignes[] = [
                'id' => $tranche->id,
                'libelle' => $tranche->libelle,
                'ordre' => $tranche->ordre,
                'pourcentage' => (float) $tranche->pourcentage,
                'date_echeance' => $tranche->date_echeance?->format('Y-m-d'),
                'montant' => $montant,
                'montant_paye' => $couvert,
                'reste' => $reste,
                'echue' => $exigible,
                'statut' => match (true) {
                    $reste === 0 => self::STATUT_SOLDEE,
                    $exigible => self::STATUT_EN_RETARD,
                    $couvert > 0 => self::STATUT_PARTIELLE,
                    default => self::STATUT_A_VENIR,
                },
            ];
        }

        return [
            'actif' => true,
            'total_du' => $totalDu,
            'total_paye' => $totalPaye,
            'du_a_ce_jour' => $duACeJour,
            'retard' => max(0, $duACeJour - $totalPaye),
            'delai_grace' => $delaiGrace,
            'prochaine_echeance' => collect($lignes)
                ->first(fn (array $ligne) => $ligne['statut'] !== self::STATUT_SOLDEE),
            'tranches' => $lignes,
        ];
    }

    /**
     * Ce qui aurait dû être versé à ce jour, moins ce qui l'a été. C'est cette
     * somme — et non le reste à payer de l'année — qui qualifie l'insolvabilité
     * dès lors qu'un échéancier existe.
     */
    public function retard(DossierScolarite $dossier, ?CarbonImmutable $aujourdHui = null): int
    {
        return $this->pourDossier($dossier, $aujourdHui)['retard'];
    }

    /**
     * Jours de tolérance après une échéance avant de compter le retard. Une
     * famille qui règle le lendemain de la date ne doit pas basculer sur la
     * liste des insolvables entre-temps.
     */
    public function delaiGrace(int $schoolId): int
    {
        return max(0, (int) Setting::get($schoolId, 'delai_grace_echeance', 0));
    }

    /**
     * Répartit un total entre les tranches selon leurs pourcentages.
     *
     * La dernière tranche reçoit le reliquat plutôt que sa part calculée : sur
     * 90 000 F en trois tiers, trois arrondis laisseraient un franc dans la
     * nature et la somme des tranches ne retomberait pas sur le dû.
     *
     * @param  Collection<int, TrancheScolarite>  $tranches
     * @return list<int>
     */
    private function repartir(int $total, Collection $tranches): array
    {
        $montants = [];
        $cumul = 0;
        $dernier = $tranches->count() - 1;

        foreach ($tranches->values() as $index => $tranche) {
            if ($index === $dernier) {
                $montants[] = max(0, $total - $cumul);

                continue;
            }

            $part = (int) round($total * (float) $tranche->pourcentage / 100);
            $montants[] = $part;
            $cumul += $part;
        }

        return $montants;
    }
}
