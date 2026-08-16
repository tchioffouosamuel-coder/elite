<?php

namespace App\Services;

use App\Models\BulletinPaie;
use App\Models\EcritureComptable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * États financiers de l'établissement.
 *
 * Tout est reconstruit depuis le **journal** (`ecritures_comptables`) et non
 * depuis les tables métier : encaissements, dépenses et salaires y déposent
 * chacun leurs lignes, et c'est la seule vue où ils se comparent sur la même
 * base. Interroger les trois tables séparément donnerait trois vérités qui
 * divergeraient à la première annulation.
 *
 * Les classes du plan comptable portent le sens : la classe 7 pour ce qui
 * entre, la 6 pour ce qui sort, la 5 pour ce qui reste en caisse.
 */
class BilanFinancierService
{
    private const CLASSE_CHARGES = 6;

    private const CLASSE_PRODUITS = 7;

    private const CLASSE_TRESORERIE = 5;

    public function __construct(private readonly ScolariteService $scolarite) {}

    /**
     * Compte de résultat de la période : produits, charges, et le solde entre
     * les deux.
     *
     * @param  array{du?: ?string, au?: ?string, annee_scolaire_id?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function resultat(int $schoolId, array $filtres = []): array
    {
        $produits = $this->parCompte($schoolId, self::CLASSE_PRODUITS, $filtres);
        $charges = $this->parCompte($schoolId, self::CLASSE_CHARGES, $filtres);

        $totalProduits = (int) $produits->sum('montant');
        $totalCharges = (int) $charges->sum('montant');

        return [
            'periode' => $this->periode($filtres),
            'produits' => ['lignes' => $produits->values(), 'total' => $totalProduits],
            'charges' => ['lignes' => $charges->values(), 'total' => $totalCharges],
            'resultat' => $totalProduits - $totalCharges,
            // Part des recettes absorbée par les charges : l'indicateur que
            // regarde un chef d'établissement avant tout autre.
            'taux_charges' => $totalProduits > 0 ? round($totalCharges * 100 / $totalProduits, 2) : 0.0,
        ];
    }

    /**
     * Solde de chaque compte de trésorerie : ce qui reste réellement en caisse,
     * en banque et sur les comptes mobiles.
     *
     * @param  array{du?: ?string, au?: ?string, annee_scolaire_id?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function tresorerie(int $schoolId, array $filtres = []): array
    {
        $lignes = $this->soldesParCompte($schoolId, self::CLASSE_TRESORERIE, $filtres)
            ->map(fn (array $l) => [
                ...$l,
                // Un compte de trésorerie est débiteur : entrées moins sorties.
                'solde' => $l['debit'] - $l['credit'],
            ])
            ->values();

        return [
            'periode' => $this->periode($filtres),
            'comptes' => $lignes,
            'disponible' => (int) $lignes->sum('solde'),
        ];
    }

    /**
     * Balance générale : débit, crédit et solde de chaque compte mouvementé.
     * C'est l'état que réclame un comptable pour contrôler la cohérence.
     *
     * @param  array{du?: ?string, au?: ?string, annee_scolaire_id?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function balance(int $schoolId, array $filtres = []): array
    {
        $lignes = $this->soldesParCompte($schoolId, null, $filtres)
            ->map(fn (array $l) => [...$l, 'solde' => $l['debit'] - $l['credit']])
            ->sortBy('code')
            ->values();

        $debit = (int) $lignes->sum('debit');
        $credit = (int) $lignes->sum('credit');

        return [
            'periode' => $this->periode($filtres),
            'lignes' => $lignes,
            'total_debit' => $debit,
            'total_credit' => $credit,
            // En partie double, les deux colonnes s'égalisent. Un écart
            // signale une écriture incomplète, pas une erreur d'arrondi.
            'equilibre' => $debit === $credit,
        ];
    }

    /**
     * Synthèse pour le tableau de bord : recouvrement de la scolarité, masse
     * salariale, dépenses et trésorerie en une seule lecture.
     *
     * @return array<string, mixed>
     */
    public function tableauDeBord(int $schoolId, int $anneeScolaireId, array $filtres = []): array
    {
        $filtres = [...$filtres, 'annee_scolaire_id' => $anneeScolaireId];

        // Même calcul que la Caisse (`ScolariteService::situation`) : un
        // dossier ne naît qu'au premier encaissement, donc interroger
        // `dossiers_scolarite` seule ne compte que les élèves déjà passés au
        // comptoir et sous-évalue le recouvrement pour tous les autres.
        $situation = $this->scolarite->situation($schoolId, $anneeScolaireId)['totaux'];

        $bulletins = BulletinPaie::forSchool($schoolId)->arretes()->get();
        $resultat = $this->resultat($schoolId, $filtres);

        return [
            'scolarite' => [
                'effectif' => $situation['effectif'],
                'attendu' => $situation['attendu'],
                'recouvre' => $situation['recouvre'],
                'reste' => $situation['reste'],
                'taux_recouvrement' => $situation['taux_recouvrement'],
                'insolvables' => $situation['insolvables'],
            ],
            'paie' => [
                'bulletins' => $bulletins->count(),
                'masse_brute' => (int) $bulletins->sum('salaire_brut'),
                'cout_employeur' => (int) $bulletins->sum(fn (BulletinPaie $b) => $b->cout_employeur),
                'a_regler' => (int) $bulletins->where('statut', 'valide')->sum('net_a_payer'),
            ],
            'resultat' => [
                'produits' => $resultat['produits']['total'],
                'charges' => $resultat['charges']['total'],
                'solde' => $resultat['resultat'],
            ],
            'tresorerie' => $this->tresorerie($schoolId, $filtres)['disponible'],
        ];
    }

    /**
     * Montants d'une classe de comptes, dans leur sens naturel : un produit se
     * lit au crédit, une charge au débit. Les contrepassations d'annulation
     * viennent en déduction puisqu'elles portent le sens inverse.
     *
     * @return Collection<int, array{code: string, libelle: string, montant: int}>
     */
    private function parCompte(int $schoolId, int $classe, array $filtres): Collection
    {
        $sensNaturel = $classe === self::CLASSE_PRODUITS ? 'credit' : 'debit';

        return $this->soldesParCompte($schoolId, $classe, $filtres)
            ->map(fn (array $l) => [
                'code' => $l['code'],
                'libelle' => $l['libelle'],
                'montant' => $sensNaturel === 'credit' ? $l['credit'] - $l['debit'] : $l['debit'] - $l['credit'],
            ])
            ->filter(fn (array $l) => $l['montant'] !== 0)
            ->sortByDesc('montant');
    }

    /**
     * Débit et crédit cumulés par compte, base commune à tous les états.
     *
     * @return Collection<int, array{code: string, libelle: string, classe: int, debit: int, credit: int}>
     */
    private function soldesParCompte(int $schoolId, ?int $classe, array $filtres): Collection
    {
        return EcritureComptable::forSchool($schoolId)
            ->when($filtres['du'] ?? null, fn ($q, $du) => $q->whereDate('date_ecriture', '>=', $du))
            ->when($filtres['au'] ?? null, fn ($q, $au) => $q->whereDate('date_ecriture', '<=', $au))
            ->when(
                $filtres['annee_scolaire_id'] ?? null,
                fn ($q, $id) => $q->where('annee_scolaire_id', $id),
            )
            ->when($classe, fn ($q, $c) => $q->whereHas('compte', fn ($cq) => $cq->where('classe', $c)))
            ->with('compte')
            ->get()
            ->filter(fn (EcritureComptable $e) => $e->compte !== null)
            ->groupBy(fn (EcritureComptable $e) => $e->compte->code)
            ->map(fn (Collection $lot, $code) => [
                // Les clés de tableau numériques deviennent des entiers en PHP :
                // le cast garde un type stable dans la réponse de l'API.
                'code' => (string) $code,
                'libelle' => $lot->first()->compte->libelle,
                'classe' => (int) $lot->first()->compte->classe,
                'debit' => (int) $lot->where('sens', 'debit')->sum('montant'),
                'credit' => (int) $lot->where('sens', 'credit')->sum('montant'),
            ])
            ->values();
    }

    /** @return array{du: ?string, au: ?string} */
    private function periode(array $filtres): array
    {
        return [
            'du' => $filtres['du'] ?? null,
            'au' => $filtres['au'] ?? Carbon::today()->toDateString(),
        ];
    }
}
