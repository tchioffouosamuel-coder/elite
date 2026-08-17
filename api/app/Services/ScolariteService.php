<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\CompteComptable;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\Versement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Encaissement des frais de scolarité.
 *
 * Deux principes tiennent tout le reste. D'abord, le **dossier** de l'élève
 * fige ce qui lui est réclamé : il recopie la grille tarifaire à l'ouverture
 * et ne la relit plus, pour qu'un tarif révisé en cours d'année ne modifie pas
 * rétroactivement ce qui a été annoncé aux familles. Ensuite, un encaissement
 * ne se supprime jamais : le reçu porte un numéro remis à la famille, une
 * erreur s'annule en gardant trace de qui, quand et pourquoi.
 */
class ScolariteService extends BaseService
{
    /** Compte de trésorerie mouvementé selon le moyen de paiement. */
    private const COMPTES_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    private const COMPTE_SCOLARITE = '701';

    private const COMPTE_FRAIS_ANNEXES = '706';

    public function __construct(private readonly NumeroRecuService $numeros) {}

    /**
     * Dossier de l'élève pour l'année, ouvert au besoin.
     *
     * À l'ouverture, le montant vient de la grille de sa classe et les frais
     * annexes obligatoires sont rattachés. Un dossier existant n'est jamais
     * réaligné sur la grille : il porte peut-être un montant négocié.
     */
    public function dossier(Eleve $eleve, AnneeScolaire $annee): DossierScolarite
    {
        $existant = DossierScolarite::where('annee_scolaire_id', $annee->id)
            ->where('eleve_id', $eleve->id)
            ->avecTotaux()
            ->first();

        if ($existant) {
            return $existant;
        }

        return $this->transaction(function () use ($eleve, $annee) {
            $dossier = DossierScolarite::create([
                'school_id' => $eleve->school_id,
                'annee_scolaire_id' => $annee->id,
                'eleve_id' => $eleve->id,
                'montant_scolarite' => $this->tarif($eleve, $annee),
                'report_dette' => $this->reliquatAnneePrecedente($eleve, $annee),
            ]);

            foreach ($this->fraisObligatoires($eleve->school_id, $annee->id) as $frais) {
                $dossier->fraisAnnexes()->create([
                    'frais_annexe_id' => $frais->id,
                    'libelle' => $frais->libelle,
                    'montant' => $frais->montant,
                ]);
            }

            return $dossier->load(['fraisAnnexes', 'versements', 'busAffectations.trajet']);
        });
    }

    /**
     * Tarif applicable : celui de la classe si elle en a un, sinon le tarif par
     * défaut de l'école (ligne sans classe).
     */
    private function tarif(Eleve $eleve, AnneeScolaire $annee): int
    {
        // Les deux candidats en une requête, sans `orWhere` imbriqué : mal
        // parenthésé, il ferait sortir la recherche du périmètre de l'école.
        $grilles = GrilleFrais::forSchool($eleve->school_id)
            ->where('annee_scolaire_id', $annee->id)
            ->where(fn ($q) => $q->whereNull('classe_id')->orWhere('classe_id', $eleve->classe_id))
            ->get();

        return (int) ($grilles->firstWhere('classe_id', $eleve->classe_id)?->montant
            ?? $grilles->firstWhere('classe_id', null)?->montant
            ?? 0);
    }

    /**
     * Reliquat de l'année précédente, repris au dossier courant : c'est la
     * « dette scolarité » que suivait l'ancienne application.
     */
    private function reliquatAnneePrecedente(Eleve $eleve, AnneeScolaire $annee): int
    {
        $precedent = DossierScolarite::where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', '!=', $annee->id)
            ->whereHas('anneeScolaire', fn ($q) => $q->where('date_debut', '<', $annee->date_debut))
            ->avecTotaux()
            ->latest('id')
            ->first();

        return $precedent?->reste_a_payer ?? 0;
    }

    /** @return Collection<int, FraisAnnexe> */
    private function fraisObligatoires(int $schoolId, int $anneeId)
    {
        return FraisAnnexe::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('obligatoire', true)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Enregistre un encaissement et rend le versement, reçu numéroté à l'appui.
     *
     * @param  array{montant: int, date_versement?: string, mode?: string, reference_externe?: ?string, note?: ?string, lignes?: list<array{affectation: string, dossier_frais_annexe_id?: ?int, libelle?: ?string, montant: int}>}  $donnees
     */
    public function encaisser(DossierScolarite $dossier, array $donnees, ?int $encaissePar = null): Versement
    {
        $montant = (int) $donnees['montant'];

        if ($montant <= 0) {
            throw new RuntimeException('Le montant encaissé doit être supérieur à zéro.');
        }

        return $this->transaction(function () use ($dossier, $donnees, $montant, $encaissePar) {
            $lignes = $donnees['lignes'] ?? $this->ventilationAutomatique($dossier, $montant);
            $this->verifierVentilation($montant, $lignes);

            $versement = Versement::create([
                'school_id' => $dossier->school_id,
                'dossier_scolarite_id' => $dossier->id,
                'numero_recu' => $this->numeros->attribuer(
                    $dossier->school,
                    $dossier->annee_scolaire_id,
                    $encaissePar,
                ),
                'date_versement' => $donnees['date_versement'] ?? Carbon::today()->toDateString(),
                'montant' => $montant,
                'mode' => $donnees['mode'] ?? 'especes',
                'reference_externe' => $donnees['reference_externe'] ?? null,
                'encaisse_par' => $encaissePar,
                'note' => $donnees['note'] ?? null,
            ]);

            foreach ($lignes as $ligne) {
                $versement->lignes()->create([
                    'affectation' => $ligne['affectation'],
                    'dossier_frais_annexe_id' => $ligne['dossier_frais_annexe_id'] ?? null,
                    'libelle' => $ligne['libelle'] ?? $this->libelleAffectation($ligne['affectation']),
                    'montant' => (int) $ligne['montant'],
                ]);
            }

            $this->comptabiliser($versement->load('lignes'), $dossier);

            return $versement;
        });
    }

    /**
     * Le reliquat de l'an dernier s'éteint en premier, puis la scolarité, puis
     * les frais annexes : c'est l'ordre que suit le comptoir, et il évite
     * qu'une dette ancienne traîne pendant qu'on solde l'année en cours.
     * S'appuie sur `DossierScolarite::rubriques`, qui décompose déjà le dû et
     * le réglé poste par poste — c'est la même donnée qui alimente l'écran
     * d'encaissement, pour que la ventilation automatique corresponde
     * exactement à ce que l'utilisateur y a vu.
     *
     * @return list<array{affectation: string, dossier_frais_annexe_id: ?int, libelle: string, montant: int}>
     */
    private function ventilationAutomatique(DossierScolarite $dossier, int $montant): array
    {
        $lignes = [];
        $restant = $montant;

        foreach ($dossier->rubriques as $rubrique) {
            if ($restant <= 0) {
                break;
            }

            $part = min($restant, $rubrique['reste']);

            if ($part > 0) {
                $lignes[] = [
                    'affectation' => $rubrique['cle'],
                    'libelle' => $rubrique['libelle'],
                    'dossier_frais_annexe_id' => $rubrique['dossier_frais_annexe_id'],
                    'montant' => $part,
                ];
                $restant -= $part;
            }
        }

        // Trop-perçu : la famille verse au-delà du dû, l'excédent reste porté
        // par la scolarité et ressortira en avance sur le dossier.
        if ($restant > 0) {
            $lignes[] = [
                'affectation' => 'scolarite',
                'libelle' => 'Avance sur scolarité',
                'dossier_frais_annexe_id' => null,
                'montant' => $restant,
            ];
        }

        return $lignes;
    }

    /** @param  list<array{montant: int}>  $lignes */
    private function verifierVentilation(int $montant, array $lignes): void
    {
        $total = array_sum(array_map(static fn ($l) => (int) $l['montant'], $lignes));

        if ($total !== $montant) {
            throw new RuntimeException(
                "La ventilation ({$total} F) ne correspond pas au montant encaissé ({$montant} F).",
            );
        }
    }

    private function libelleAffectation(string $affectation): string
    {
        return match ($affectation) {
            'report_dette' => 'Reliquat année précédente',
            'frais_annexe' => 'Frais annexe',
            'bus' => 'Transport scolaire',
            default => 'Frais de scolarité',
        };
    }

    /**
     * Journal : la caisse est débitée du montant reçu, les comptes de produits
     * crédités poste par poste. Sans ces écritures, le bilan devrait
     * reconstituer les recettes en parcourant les reçus un à un.
     */
    private function comptabiliser(Versement $versement, DossierScolarite $dossier): void
    {
        $commun = [
            'school_id' => $versement->school_id,
            'annee_scolaire_id' => $dossier->annee_scolaire_id,
            'date_ecriture' => $versement->date_versement,
            'origine_type' => $versement->getMorphClass(),
            'origine_id' => $versement->id,
        ];

        EcritureComptable::create($commun + [
            'libelle' => 'Encaissement scolarité — reçu '.$versement->numero_recu,
            'montant' => $versement->montant,
            'sens' => 'debit',
            'compte_comptable_id' => $this->compte(self::COMPTES_TRESORERIE[$versement->mode] ?? '571'),
        ]);

        foreach ($versement->lignes as $ligne) {
            EcritureComptable::create($commun + [
                'libelle' => $ligne->libelle.' — reçu '.$versement->numero_recu,
                'montant' => $ligne->montant,
                'sens' => 'credit',
                'compte_comptable_id' => $this->compte(
                    in_array($ligne->affectation, ['frais_annexe', 'bus'], true)
                        ? self::COMPTE_FRAIS_ANNEXES
                        : self::COMPTE_SCOLARITE,
                ),
            ]);
        }
    }

    private function compte(string $code): ?int
    {
        return CompteComptable::where('code', $code)->value('id');
    }

    /**
     * Situation de recouvrement d'une école, filtrable par classe et par
     * statut. Sert aussi bien au tableau de bord qu'à la liste des insolvables :
     * c'est la même donnée, seul le filtre change.
     *
     * @param  array{classe_id?: ?int, statut?: ?string}  $filtres
     * @return array{dossiers: Collection, totaux: array<string, int|float>}
     */
    public function situation(int $schoolId, int $anneeScolaireId, array $filtres = []): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        /*
         * On part des **élèves**, pas des dossiers.
         *
         * Un dossier ne naît qu'au premier encaissement : le lister seul
         * laissait la caisse vide tant que personne n'avait payé, donc
         * indéfiniment — on ne pouvait jamais encaisser un premier versement.
         * Les élèves sans dossier apparaissent avec le montant projeté depuis
         * la grille tarifaire, et leur dossier s'ouvrira au comptoir.
         */
        $eleves = Eleve::forSchool($schoolId)
            ->where('statut', 'actif')
            ->when($filtres['classe_id'] ?? null, fn ($q, $classeId) => $q->where('classe_id', $classeId))
            ->with(['classe', 'tuteurs'])
            ->orderBy('nom_complet')
            ->get();

        $dossiers = DossierScolarite::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->avecTotaux()
            ->get()
            ->keyBy('eleve_id');

        // Projection commune aux élèves sans dossier : le tarif ne dépend que
        // de la classe, autant ne pas relire la grille élève par élève.
        $fraisObligatoires = (int) $this->fraisObligatoires($schoolId, $anneeScolaireId)->sum('montant');

        // Souscriptions bus actives, groupées par élève : un dossier projeté
        // (jamais ouvert) n'a aucune relation chargée depuis la base et doit
        // les recevoir explicitement pour que le transport compte dans le dû.
        $busParEleve = BusAffectation::whereIn('eleve_id', $eleves->pluck('id'))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->actives()
            ->with('trajet')
            ->get()
            ->groupBy('eleve_id');

        $lignes = $eleves
            ->map(function (Eleve $eleve) use ($dossiers, $annee, $fraisObligatoires, $busParEleve) {
                $dossier = $dossiers->get($eleve->id);

                if ($dossier) {
                    $dossier->setRelation('eleve', $eleve);

                    return $dossier;
                }

                // Dossier projeté, non enregistré : il porte `id = null`, ce qui
                // signale à l'interface qu'il faut l'ouvrir avant d'encaisser.
                return $this->dossierProjete($eleve, $annee, $fraisObligatoires, $busParEleve->get($eleve->id, new EloquentCollection));
            })
            // Le statut se déduit des versements : il ne peut pas se filtrer en
            // SQL sans dupliquer le calcul des soldes dans la requête.
            ->when(
                $filtres['statut'] ?? null,
                fn ($c, $statut) => $c->where('statut_paiement', $statut),
            )
            ->values();

        $attendu = (int) $lignes->sum('total_du');
        $recouvre = (int) $lignes->sum('total_paye');

        return [
            'dossiers' => $lignes,
            'totaux' => [
                'effectif' => $lignes->count(),
                'attendu' => $attendu,
                'recouvre' => $recouvre,
                'reste' => (int) $lignes->sum('reste_a_payer'),
                'avances' => (int) $lignes->sum('avance'),
                'taux_recouvrement' => $attendu > 0 ? round($recouvre * 100 / $attendu, 2) : 0.0,
                'insolvables' => $lignes->whereIn('statut_paiement', ['impaye', 'partiel'])->count(),
            ],
        ];
    }

    /**
     * Ce que devra l'élève si son dossier était ouvert aujourd'hui. Non
     * persisté : ouvrir 269 dossiers à la simple consultation d'un écran
     * écrirait en base sur une lecture, et créerait un dossier à des élèves
     * qui ne paieront jamais par ce guichet.
     */
    private function dossierProjete(Eleve $eleve, AnneeScolaire $annee, int $fraisObligatoires, ?EloquentCollection $busAffectations = null): DossierScolarite
    {
        $dossier = new DossierScolarite([
            'school_id' => $eleve->school_id,
            'annee_scolaire_id' => $annee->id,
            'eleve_id' => $eleve->id,
            'montant_scolarite' => $this->tarif($eleve, $annee),
            'report_dette' => $this->reliquatAnneePrecedente($eleve, $annee),
        ]);

        $dossier->setRelation('eleve', $eleve);
        $dossier->setRelation('versements', new EloquentCollection);
        $dossier->setRelation('busAffectations', $busAffectations ?? new EloquentCollection);
        // Une seule ligne fictive porte le total des frais obligatoires : le
        // détail n'a d'intérêt qu'une fois le dossier réellement ouvert.
        $dossier->setRelation('fraisAnnexes', new EloquentCollection(
            $fraisObligatoires > 0
                ? [new DossierFraisAnnexe(['libelle' => 'Frais annexes obligatoires', 'montant' => $fraisObligatoires])]
                : [],
        ));

        return $dossier;
    }

    /**
     * Annule un encaissement : le versement reste au registre, ses écritures
     * sont contrepassées plutôt que supprimées — un journal comptable ne se
     * réécrit pas, il se corrige par une écriture inverse.
     */
    public function annuler(Versement $versement, string $motif, ?int $annulePar = null): Versement
    {
        if ($versement->estAnnule()) {
            throw new RuntimeException('Ce reçu est déjà annulé.');
        }

        return $this->transaction(function () use ($versement, $motif, $annulePar) {
            $versement->update([
                'annule_le' => now(),
                'annule_par' => $annulePar,
                'motif_annulation' => $motif,
            ]);

            foreach ($versement->ecritures()->get() as $ecriture) {
                EcritureComptable::create([
                    'school_id' => $ecriture->school_id,
                    'annee_scolaire_id' => $ecriture->annee_scolaire_id,
                    'date_ecriture' => now()->toDateString(),
                    'libelle' => 'Annulation — '.$ecriture->libelle,
                    'montant' => $ecriture->montant,
                    'sens' => $ecriture->sens === 'debit' ? 'credit' : 'debit',
                    'compte_comptable_id' => $ecriture->compte_comptable_id,
                    'origine_type' => $versement->getMorphClass(),
                    'origine_id' => $versement->id,
                ]);
            }

            return $versement->fresh();
        });
    }
}
