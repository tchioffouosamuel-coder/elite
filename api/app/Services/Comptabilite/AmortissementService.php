<?php

namespace App\Services\Comptabilite;

use App\Models\Amortissement;
use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\EcritureComptable;
use App\Models\Immobilisation;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Amortissement des biens immobilisés.
 *
 * Classer la construction en investissement l'a sortie du résultat ; sans
 * dotation, elle en disparaissait purement, ce qui est l'erreur inverse de
 * celle du classeur. La dotation la ramène, étalée sur la durée du bien : le
 * compte 699 cesse d'être la ligne à zéro qu'il était sur onze exercices.
 *
 * Le calcul est linéaire et rejouable : une dotation par bien et par exercice,
 * la dernière soldant le reliquat pour que le cumul tombe exactement sur le
 * montant du bien.
 */
class AmortissementService extends BaseService
{
    /** Charge de l'exercice, et sa contrepartie au bilan. */
    private const COMPTE_DOTATION = '699';

    private const COMPTE_CUMUL = '281';

    /**
     * Inscrit à l'actif une dépense portée sur un compte d'investissement.
     *
     * Appelé à l'enregistrement de la dépense : c'est le seul moment où l'on
     * sait de quel compte elle relève et quel justificatif elle porte. Une
     * dépense de fonctionnement passe ici sans rien produire.
     */
    public function immobiliserDepense(Depense $depense): ?Immobilisation
    {
        $compte = $depense->compte_comptable_id
            ? CompteComptable::find($depense->compte_comptable_id)
            : null;

        if (! $compte || $compte->nature !== 'investissement') {
            return null;
        }

        return Immobilisation::create([
            'school_id' => $depense->school_id,
            'depense_id' => $depense->id,
            'compte_comptable_id' => $compte->id,
            'libelle' => $depense->libelle,
            'montant' => $depense->montant,
            'date_mise_en_service' => $depense->date_depense,
            'duree_annees' => (int) config('comptabilite.duree_amortissement_batiments'),
        ]);
    }

    /**
     * Révise le libellé et la durée d'étalement d'un bien.
     *
     * Le compte 624 mêle construction et réfection : vingt ans conviennent à
     * un bâtiment, pas à une toiture. La durée par défaut n'est donc qu'un
     * point de départ, et se corrige tant que le bien s'amortit encore.
     *
     * Les dotations déjà passées ne sont pas recalculées — un exercice clos ne
     * se réécrit pas. La nouvelle durée vaut pour les annuités à venir, et le
     * garde-fou du reliquat fait que le cumul tombe toujours juste.
     *
     * @param  array{libelle?: string, duree_annees?: int}  $donnees
     */
    public function reviser(Immobilisation $bien, array $donnees): Immobilisation
    {
        if (isset($donnees['duree_annees'])) {
            $ecoulees = $bien->amortissements()->count();

            if ((int) $donnees['duree_annees'] < $ecoulees) {
                throw new RuntimeException(
                    "Ce bien porte déjà {$ecoulees} annuité(s) : la durée ne peut pas descendre en dessous.",
                );
            }
        }

        $bien->update(array_filter([
            'libelle' => $donnees['libelle'] ?? null,
            'duree_annees' => $donnees['duree_annees'] ?? null,
        ], static fn ($valeur) => $valeur !== null));

        return $bien->fresh(['amortissements']);
    }

    /**
     * Ce que l'exercice doit doter, bien par bien, sans rien écrire.
     *
     * @return list<array{immobilisation_id: int, libelle: string, montant: int, duree_annees: int, cumul: int, valeur_residuelle: int, dotation: int, deja_dote: bool}>
     */
    public function projeter(int $schoolId, int $anneeScolaireId): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        return $this->biens($schoolId, $annee)->map(function (Immobilisation $bien) use ($anneeScolaireId) {
            $dejaDote = $bien->amortissements->firstWhere('annee_scolaire_id', $anneeScolaireId);

            return [
                'immobilisation_id' => $bien->id,
                'libelle' => $bien->libelle,
                'montant' => $bien->montant,
                // La durée se corrige depuis l'écran : elle doit y remonter.
                'duree_annees' => $bien->duree_annees,
                'cumul' => $bien->cumul_amorti,
                'valeur_residuelle' => $bien->valeur_residuelle,
                'dotation' => $dejaDote?->montant ?? $this->dotation($bien),
                'deja_dote' => $dejaDote !== null,
            ];
        })->values()->all();
    }

    /**
     * Passe les dotations manquantes de l'exercice.
     *
     * @return list<array<string, mixed>>
     */
    public function doter(int $schoolId, int $anneeScolaireId): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        return $this->transaction(function () use ($schoolId, $annee, $anneeScolaireId) {
            $passees = [];

            foreach ($this->biens($schoolId, $annee) as $bien) {
                if ($bien->amortissements->firstWhere('annee_scolaire_id', $anneeScolaireId)) {
                    continue;
                }

                $montant = $this->dotation($bien);

                if ($montant <= 0) {
                    continue;
                }

                $dotation = Amortissement::create([
                    'immobilisation_id' => $bien->id,
                    'annee_scolaire_id' => $anneeScolaireId,
                    'montant' => $montant,
                    'date_dotation' => $annee->date_fin->toDateString(),
                ]);

                $this->comptabiliser($bien, $dotation, $annee);

                $passees[] = [
                    'immobilisation_id' => $bien->id,
                    'libelle' => $bien->libelle,
                    'montant' => $montant,
                ];
            }

            return $passees;
        });
    }

    /**
     * Dotation linéaire, bornée par ce qui reste à amortir : la dernière
     * annuité solde le reliquat plutôt que de dépasser la valeur du bien.
     */
    private function dotation(Immobilisation $bien): int
    {
        return min($bien->dotationAnnuelle(), $bien->valeur_residuelle);
    }

    /**
     * Biens en service à la clôture de l'exercice. Un bien mis en service
     * après la fin de l'exercice ne se dote pas encore.
     *
     * @return Collection<int, Immobilisation>
     */
    private function biens(int $schoolId, AnneeScolaire $annee): Collection
    {
        return Immobilisation::forSchool($schoolId)
            ->enService()
            ->where('date_mise_en_service', '<=', $annee->date_fin)
            ->with('amortissements')
            ->orderBy('date_mise_en_service')
            ->get();
    }

    /** Charge au débit, amortissements cumulés au crédit. */
    private function comptabiliser(Immobilisation $bien, Amortissement $dotation, AnneeScolaire $annee): void
    {
        $commun = [
            'school_id' => $bien->school_id,
            'annee_scolaire_id' => $annee->id,
            'date_ecriture' => $dotation->date_dotation,
            'montant' => $dotation->montant,
            'origine_type' => $dotation->getMorphClass(),
            'origine_id' => $dotation->id,
        ];

        foreach ([[self::COMPTE_DOTATION, 'debit'], [self::COMPTE_CUMUL, 'credit']] as [$code, $sens]) {
            EcritureComptable::create($commun + [
                'libelle' => 'Dotation aux amortissements — '.$bien->libelle,
                'sens' => $sens,
                'compte_comptable_id' => CompteComptable::where('code', $code)->value('id'),
            ]);
        }
    }
}
