<?php

namespace App\Services\Comptabilite;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use Illuminate\Support\Collection;

/**
 * « État de synthèse des charges et dépenses » d'un exercice.
 *
 * Reproduit le document que tient l'établissement : une colonne de dépenses
 * dans l'ordre du plan, un total, une colonne de produits, un total, et la
 * balance de fin d'exercice. Un comptable doit pouvoir poser son classeur à
 * côté de l'écran et retrouver ses lignes.
 *
 * Mais le document additionne trois choses de natures différentes — les
 * charges de l'année, la construction des bâtiments et les apports de
 * l'exploitant — et son solde n'a donc pas le sens qu'on lui prête. Sur onze
 * exercices, le compte 624 pèse 202 millions et le dépôt de l'exploitant 25
 * millions : retirés du calcul, le déficit cumulé de 38,6 millions devient un
 * excédent d'exploitation de 188 millions.
 *
 * D'où deux lectures rendues côte à côte, sans que l'une remplace l'autre :
 * `document` reproduit le classeur tel quel, `analytique` sépare ce qui use
 * l'exercice de ce qui le dépasse.
 */
class EtatSyntheseService
{
    /** Compte de l'apport de l'exploitant, sous la balance dans le document. */
    private const COMPTE_APPORT = '108';

    /**
     * @return array{
     *     exercice: array{annee_scolaire_id: int, libelle: string, school_id: int, effectif: int},
     *     depenses: list<array<string, mixed>>,
     *     produits: list<array<string, mixed>>,
     *     document: array{total_depenses: int, total_recettes: int, balance: int, apport_fondateur: int},
     *     analytique: array<string, int>
     * }
     */
    public function etablir(int $schoolId, int $anneeScolaireId): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);
        $soldes = $this->soldesParCompte($schoolId, $anneeScolaireId);

        /*
         * Les comptes désactivés — reliquats d'un plan antérieur — n'ont pas à
         * encombrer la grille. Mais un compte retiré du plan alors qu'il porte
         * encore des écritures doit rester visible : le masquer ferait
         * disparaître son montant du total sans que rien ne le signale.
         */
        $comptes = CompteComptable::query()
            ->where(fn ($q) => $q->whereIn('classe', [6, 7])->orWhere('code', '100'))
            ->orderBy('ordre')
            ->get()
            ->filter(fn (CompteComptable $c) => $c->is_active || $this->solde($soldes, $c->code) !== 0);

        $depenses = $this->lignes($comptes->filter(fn (CompteComptable $c) => $c->classe === 6 || $c->code === '100'), $soldes);
        $produits = $this->lignes($comptes->filter(fn (CompteComptable $c) => $c->classe === 7), $soldes);

        // Le document additionne tout ce que porte sa colonne, dépôt compris :
        // on le reproduit sans le corriger, c'est ce total-là que le comptable
        // reconnaît.
        $totalDepenses = (int) $depenses->sum('montant');
        $totalRecettes = (int) $produits->sum('montant');

        return [
            'exercice' => [
                'annee_scolaire_id' => $annee->id,
                'libelle' => $annee->libelle,
                'school_id' => $schoolId,
                'effectif' => $this->effectif($schoolId, $anneeScolaireId),
            ],
            'depenses' => $depenses->values()->all(),
            'produits' => $produits->values()->all(),
            'document' => [
                'total_depenses' => $totalDepenses,
                'total_recettes' => $totalRecettes,
                'balance' => $totalRecettes - $totalDepenses,
                'apport_fondateur' => $this->solde($soldes, self::COMPTE_APPORT),
            ],
            'analytique' => $this->analytique($depenses, $produits),
        ];
    }

    /**
     * Les onze exercices d'un établissement en une seule requête de lecture,
     * pour la série que le document ne donne qu'en feuilletant onze onglets.
     *
     * @return list<array<string, mixed>>
     */
    public function serie(int $schoolId): array
    {
        return AnneeScolaire::where('school_id', $schoolId)
            ->orderBy('date_debut')
            ->get()
            ->map(function (AnneeScolaire $annee) use ($schoolId) {
                $etat = $this->etablir($schoolId, $annee->id);

                return [
                    'annee_scolaire_id' => $annee->id,
                    'libelle' => $annee->libelle,
                    'effectif' => $etat['exercice']['effectif'],
                    ...$etat['document'],
                    'resultat_exploitation' => $etat['analytique']['resultat_exploitation'],
                    'investissement' => $etat['analytique']['investissement'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Ce que le document mélange, séparé.
     *
     * Le résultat d'exploitation ne retient que les comptes qui usent
     * l'exercice ; la construction et les apports sont rendus à part, avec le
     * solde que le document aurait affiché si on les lui avait retirés.
     *
     * @param  Collection<int, array<string, mixed>>  $depenses
     * @param  Collection<int, array<string, mixed>>  $produits
     * @return array<string, int>
     */
    private function analytique(Collection $depenses, Collection $produits): array
    {
        $parNature = fn (Collection $lignes, string $nature) => (int) $lignes
            ->where('nature', $nature)->sum('montant');

        $chargesExploitation = $parNature($depenses, 'exploitation');
        $produitsExploitation = $parNature($produits, 'exploitation');

        return [
            'charges_exploitation' => $chargesExploitation,
            'produits_exploitation' => $produitsExploitation,
            'resultat_exploitation' => $produitsExploitation - $chargesExploitation,
            // Construction des bâtiments : un actif, pas une charge de l'année.
            'investissement' => $parNature($depenses, 'investissement'),
            // Dépôt et apports de l'exploitant : haut de bilan.
            'capital' => $parNature($depenses, 'capital'),
        ];
    }

    /**
     * @param  Collection<int, CompteComptable>  $comptes
     * @param  Collection<string, int>  $soldes
     * @return Collection<int, array<string, mixed>>
     */
    private function lignes(Collection $comptes, Collection $soldes): Collection
    {
        return $comptes->map(fn (CompteComptable $compte) => [
            'code' => $compte->code,
            'libelle' => $compte->libelle,
            'libelle_en' => $compte->libelle_en,
            'nature' => $compte->nature,
            'assiette' => $compte->assiette,
            'montant_unitaire' => $compte->montant_unitaire,
            'montant' => $this->solde($soldes, $compte->code),
        ])->values();
    }

    /**
     * Solde d'un compte dans son sens naturel : une charge se lit au débit
     * moins le crédit (une contrepassation d'annulation la diminue), un
     * produit dans l'autre sens.
     *
     * @return Collection<string, int>
     */
    private function soldesParCompte(int $schoolId, int $anneeScolaireId): Collection
    {
        return EcritureComptable::query()
            ->join('comptes_comptables', 'comptes_comptables.id', '=', 'ecritures_comptables.compte_comptable_id')
            ->where('ecritures_comptables.school_id', $schoolId)
            ->where('ecritures_comptables.annee_scolaire_id', $anneeScolaireId)
            ->groupBy('comptes_comptables.code', 'comptes_comptables.sens')
            ->selectRaw('comptes_comptables.code as code, comptes_comptables.sens as sens')
            ->selectRaw("SUM(CASE WHEN ecritures_comptables.sens = 'debit' THEN montant ELSE 0 END) as total_debit")
            ->selectRaw("SUM(CASE WHEN ecritures_comptables.sens = 'credit' THEN montant ELSE 0 END) as total_credit")
            ->get()
            ->mapWithKeys(fn ($ligne) => [
                $ligne->code => (int) ($ligne->sens === 'debit'
                    ? $ligne->total_debit - $ligne->total_credit
                    : $ligne->total_credit - $ligne->total_debit),
            ]);
    }

    /** @param Collection<string, int> $soldes */
    private function solde(Collection $soldes, string $code): int
    {
        return (int) $soldes->get($code, 0);
    }

    /**
     * Effectif de l'exercice : un dossier de scolarité par élève inscrit.
     * C'est lui qui commande les recettes comme les prélèvements par élève.
     */
    private function effectif(int $schoolId, int $anneeScolaireId): int
    {
        return DossierScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->count();
    }
}
