<?php

namespace App\Services\Paie;

use App\Models\BulletinPaie;
use Illuminate\Support\Collection;

/**
 * Bordereau de virement des salaires du mois.
 *
 * Dernière étape du circuit de paie : ce qui part à la banque. Les registres
 * de l'établissement en tiennent un par banque — trois se partagent les
 * comptes du personnel — chacun nominatif, avec le numéro de compte et le
 * montant arrondi.
 *
 * Le montant viré n'est pas le net du bulletin : c'est le net diminué des
 * retenues internes du mois, puis arrondi. Le classeur pratique un arrondi à
 * la centaine inférieure, l'appoint restant en caisse.
 */
class BordereauVirementService
{
    /**
     * @return array{
     *     periode: array{annee: int, mois: int},
     *     banques: list<array{banque: string, effectif: int, total: int, lignes: list<array<string, mixed>>}>,
     *     total: int,
     *     sans_domiciliation: list<array<string, mixed>>
     * }
     */
    public function etablir(int $schoolId, int $annee, int $mois): array
    {
        $bulletins = BulletinPaie::where('school_id', $schoolId)
            ->where('annee', $annee)
            ->where('mois', $mois)
            // Un brouillon n'engage rien : on ne vire que ce qui est arrêté.
            ->whereIn('statut', ['valide', 'paye'])
            ->with('personnel')
            ->get();

        $lignes = $bulletins
            ->map(fn (BulletinPaie $b) => $this->ligne($b))
            ->sortBy('nom_complet')
            ->values();

        // Un agent sans banque ni compte ne peut pas être viré : il est sorti
        // du bordereau et signalé, plutôt que d'y figurer sans destination.
        [$virables, $sansDomiciliation] = $lignes->partition(
            fn (array $ligne) => $ligne['banque'] !== null && $ligne['numero_compte'] !== null,
        );

        $banques = $virables
            ->groupBy('banque')
            ->map(fn (Collection $groupe, string $banque) => [
                'banque' => $banque,
                'effectif' => $groupe->count(),
                'total' => (int) $groupe->sum('montant'),
                'lignes' => $groupe->values()->all(),
            ])
            ->sortBy('banque')
            ->values();

        return [
            'periode' => ['annee' => $annee, 'mois' => $mois],
            'banques' => $banques->all(),
            'total' => (int) $banques->sum('total'),
            'sans_domiciliation' => $sansDomiciliation->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function ligne(BulletinPaie $bulletin): array
    {
        $montant = $this->arrondir($bulletin->net_a_payer);

        return [
            'bulletin_id' => $bulletin->id,
            'numero' => $bulletin->numero,
            'personnel_id' => $bulletin->personnel_id,
            'nom_complet' => $bulletin->personnel?->nom_complet,
            'matricule' => $bulletin->personnel?->matricule,
            'banque' => $bulletin->personnel?->banque,
            'numero_compte' => $bulletin->personnel?->numero_compte,
            'net_a_payer' => $bulletin->net_a_payer,
            'montant' => $montant,
            // L'appoint ne part pas à la banque : il reste en caisse.
            'arrondi' => $bulletin->net_a_payer - $montant,
        ];
    }

    /** Arrondi à la centaine inférieure, comme au bordereau papier. */
    private function arrondir(int $montant): int
    {
        $pas = max(1, (int) config('paie.bordereau.arrondi'));

        return intdiv($montant, $pas) * $pas;
    }
}
