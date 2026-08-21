<?php

namespace App\Services\Comptabilite;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\DossierScolarite;
use App\Services\BaseService;
use App\Services\DepenseService;
use Illuminate\Support\Collection;

/**
 * Prélèvements assis sur l'effectif.
 *
 * Trois charges de l'établissement ne s'arbitrent pas : la quote-part SEDUC et
 * la cotisation Fenasco-B à 200 F par élève, l'assurance scolaire à 100 F. Le
 * rapport se vérifie à l'unité près sur les onze exercices du classeur — 1 105
 * élèves donnent 221 000, 221 000 et 110 500 F.
 *
 * Les saisir à la main revient à recopier une multiplication, et à laisser
 * l'écart s'installer quand l'effectif bouge en cours d'année. Ils sont donc
 * recalculés à la demande : le service compare ce que l'effectif commande à ce
 * qui est déjà enregistré, et ne passe que la différence.
 */
class PrelevementsEleveService extends BaseService
{
    public function __construct(private readonly DepenseService $depenses) {}

    /**
     * Ce que l'effectif commande, compte par compte, sans rien écrire.
     *
     * @return list<array{code: string, libelle: string, effectif: int, montant_unitaire: int, du: int, enregistre: int, ecart: int}>
     */
    public function projeter(int $schoolId, int $anneeScolaireId): array
    {
        $effectif = $this->effectif($schoolId, $anneeScolaireId);

        return $this->comptes()->map(function (CompteComptable $compte) use ($schoolId, $anneeScolaireId, $effectif) {
            $du = $effectif * (int) $compte->montant_unitaire;
            $enregistre = $this->enregistre($schoolId, $anneeScolaireId, $compte->id);

            return [
                'code' => $compte->code,
                'libelle' => $compte->libelle,
                'effectif' => $effectif,
                'montant_unitaire' => (int) $compte->montant_unitaire,
                'du' => $du,
                'enregistre' => $enregistre,
                'ecart' => $du - $enregistre,
            ];
        })->values()->all();
    }

    /**
     * Passe la différence entre le dû et l'enregistré, compte par compte.
     *
     * Une régularisation plutôt qu'une réécriture : les dépenses déjà passées
     * portent un justificatif et des écritures, on ne les efface pas parce que
     * trois élèves se sont inscrits depuis. Un écart négatif — effectif en
     * baisse — donne une dépense négative, qui contrepasse le trop-comptabilisé.
     *
     * @return list<array<string, mixed>>
     */
    public function regulariser(int $schoolId, int $anneeScolaireId, ?int $saisiPar = null): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        return $this->transaction(function () use ($schoolId, $anneeScolaireId, $annee, $saisiPar) {
            $passees = [];

            foreach ($this->projeter($schoolId, $anneeScolaireId) as $ligne) {
                if ($ligne['ecart'] === 0) {
                    continue;
                }

                $compte = CompteComptable::where('code', $ligne['code'])->firstOrFail();

                $this->depenses->enregistrer($schoolId, [
                    'annee_scolaire_id' => $anneeScolaireId,
                    'compte_comptable_id' => $compte->id,
                    'date_depense' => $this->dateArrete($annee),
                    'libelle' => sprintf(
                        '%s — %d élèves × %s F',
                        $compte->libelle,
                        $ligne['effectif'],
                        number_format($ligne['montant_unitaire'], 0, ',', ' '),
                    ),
                    'montant' => abs($ligne['ecart']),
                    'mode' => 'virement',
                    'beneficiaire' => $compte->libelle,
                    'responsable' => 'Calcul automatique sur effectif',
                    'statut' => 'payee',
                ], $saisiPar);

                $passees[] = $ligne;
            }

            return $passees;
        });
    }

    /** @return Collection<int, CompteComptable> */
    private function comptes(): Collection
    {
        return CompteComptable::parEleve()->whereNotNull('montant_unitaire')->orderBy('ordre')->get();
    }

    private function effectif(int $schoolId, int $anneeScolaireId): int
    {
        return DossierScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->count();
    }

    /** Somme déjà passée sur ce compte pour l'exercice, annulations déduites. */
    private function enregistre(int $schoolId, int $anneeScolaireId, int $compteId): int
    {
        return (int) Depense::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('compte_comptable_id', $compteId)
            ->where('statut', '!=', 'annulee')
            ->sum('montant');
    }

    /**
     * Un prélèvement sur effectif s'arrête à la date du jour tant que
     * l'exercice court, et à sa clôture une fois celui-ci terminé : le porter
     * après la fin de l'exercice le ferait tomber dans le suivant.
     */
    private function dateArrete(AnneeScolaire $annee): string
    {
        return now()->lessThan($annee->date_fin)
            ? now()->toDateString()
            : $annee->date_fin->toDateString();
    }
}
