<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\CompteComptable;
use App\Models\DetteAnterieure;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\Moratoire;
use App\Models\Remise;
use App\Models\School;
use App\Models\Setting;
use App\Models\Versement;
use App\Services\Sms\SmsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Encaissement des frais de scolarité.
 *
 * Deux principes tiennent tout le reste. D'abord, le montant de scolarité
 * d'un dossier suit la grille tarifaire en continu : une révision de tarif
 * se répercute aussitôt sur tous les dossiers concernés, reste à payer
 * compris (cf. `synchroniserTarifs`, appelée par `TarifsController` à chaque
 * modification de grille). Les frais annexes déjà rattachés à un dossier, en
 * revanche, ne suivent pas le catalogue : leur libellé et leur montant sont
 * recopiés une fois pour toutes à l'ouverture, pour que les reçus déjà émis
 * restent exacts. Ensuite, un encaissement ne se supprime jamais : le reçu
 * porte un numéro remis à la famille, une erreur s'annule en gardant trace de
 * qui, quand et pourquoi.
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

    private const COMPTE_FRAIS_ANNEXES = '703';

    public function __construct(
        private readonly NumeroRecuService $numeros,
        private readonly EcheancierService $echeancier,
        private readonly SmsService $sms,
    ) {}

    /**
     * Dossier de l'élève pour l'année, ouvert au besoin.
     *
     * À l'ouverture, le montant vient de la grille de sa classe et les frais
     * annexes obligatoires applicables à cette classe sont rattachés. Un
     * dossier existant n'est pas réaligné ici — sa scolarité suit la grille en
     * continu via `synchroniserTarifs()`, appelée séparément quand la grille
     * change, plutôt qu'à chaque relecture du dossier.
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
            $dettesNonImputees = DetteAnterieure::where('eleve_id', $eleve->id)->nonImputees()->get();

            $dossier = DossierScolarite::create([
                'school_id' => $eleve->school_id,
                'annee_scolaire_id' => $annee->id,
                'eleve_id' => $eleve->id,
                'montant_scolarite' => $this->tarif($eleve, $annee),
                'remise' => $this->remiseAccordee($eleve->id, $annee->id),
                'report_dette' => $this->reliquatAnneePrecedente($eleve, $annee) + (int) $dettesNonImputees->sum('montant'),
            ]);

            // Les dettes antérieures viennent de gonfler `report_dette` du
            // dossier qui s'ouvre : on les marque imputées pour qu'une relecture
            // du dossier ne les rajoute pas une seconde fois.
            DetteAnterieure::whereIn('id', $dettesNonImputees->pluck('id'))
                ->update(['imputee_dossier_id' => $dossier->id]);

            foreach ($this->fraisObligatoires($eleve->school_id, $annee->id, $eleve->classe_id) as $frais) {
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
     * Réaligne le montant de scolarité de tous les dossiers déjà ouverts d'une
     * école sur la grille tarifaire courante — appelée par `TarifsController`
     * chaque fois qu'un tarif est enregistré ou retiré. Recalculée via
     * `tarif()` élève par élève plutôt que ciblée sur la seule classe modifiée :
     * un dossier dont la classe n'a pas de ligne propre dépend du tarif par
     * défaut, donc une modification de celui-ci le concerne aussi.
     *
     * Chaque famille dont le montant change en est avertie par SMS : la
     * révision est rétroactive sur un montant déjà annoncé, elle ne doit donc
     * jamais rester silencieuse.
     *
     * @return int nombre de dossiers dont le montant a changé
     */
    public function synchroniserTarifs(int $schoolId, AnneeScolaire $annee): int
    {
        $dossiers = DossierScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $annee->id)
            ->with('eleve.tuteurs')
            ->get();

        $misAJour = 0;

        foreach ($dossiers as $dossier) {
            if (! $dossier->eleve) {
                continue;
            }

            $ancienMontant = $dossier->montant_scolarite;
            $nouveauMontant = $this->tarif($dossier->eleve, $annee);

            if ($nouveauMontant !== $ancienMontant) {
                $dossier->update(['montant_scolarite' => $nouveauMontant]);
                $misAJour++;

                $this->notifierRevisionTarif($dossier->eleve, $annee, $ancienMontant, $nouveauMontant);
            }
        }

        return $misAJour;
    }

    /** Avertit le tuteur principal (ou à défaut le premier) qu'un montant déjà annoncé vient de changer. */
    private function notifierRevisionTarif(Eleve $eleve, AnneeScolaire $annee, int $ancienMontant, int $nouveauMontant): void
    {
        $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

        if (! $tuteur?->telephone) {
            return;
        }

        $sens = $nouveauMontant > $ancienMontant ? 'révisé à la hausse' : 'révisé à la baisse';
        $message = sprintf(
            'Le tarif de scolarité de %s pour %s a été %s : %s F CFA au lieu de %s F CFA.',
            $eleve->nom_complet,
            $annee->libelle,
            $sens,
            number_format($nouveauMontant, 0, ',', ' '),
            number_format($ancienMontant, 0, ',', ' '),
        );

        $this->sms->envoyer($tuteur->telephone, $message);
    }

    /** Somme des remises individuelles accordées à l'élève pour cette année — ce que `dossiers_scolarite.remise` reflète. */
    private function remiseAccordee(int $eleveId, int $anneeScolaireId): int
    {
        return (int) Remise::where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeScolaireId)->sum('montant');
    }

    /**
     * Enregistre une remise individuelle et répercute aussitôt son effet sur
     * le dossier de l'année s'il est déjà ouvert — sans ça, une remise
     * accordée après la première consultation resterait invisible jusqu'à la
     * prochaine ouverture de dossier, qui n'arrive jamais pour un dossier déjà créé.
     */
    public function enregistrerRemise(Eleve $eleve, AnneeScolaire $annee, int $montant, ?string $motif, ?int $accordePar): Remise
    {
        if ($montant <= 0) {
            throw new RuntimeException('Le montant de la remise doit être supérieur à zéro.');
        }

        return $this->transaction(function () use ($eleve, $annee, $montant, $motif, $accordePar) {
            $remise = Remise::create([
                'school_id' => $eleve->school_id,
                'eleve_id' => $eleve->id,
                'annee_scolaire_id' => $annee->id,
                'montant' => $montant,
                'motif' => $motif,
                'accorde_par' => $accordePar,
            ]);

            $this->resynchroniserRemise($eleve->id, $annee->id);

            return $remise;
        });
    }

    public function supprimerRemise(Remise $remise): void
    {
        $this->transaction(function () use ($remise) {
            $eleveId = $remise->eleve_id;
            $anneeId = $remise->annee_scolaire_id;
            $remise->delete();
            $this->resynchroniserRemise($eleveId, $anneeId);
        });
    }

    private function resynchroniserRemise(int $eleveId, int $anneeScolaireId): void
    {
        DossierScolarite::where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->update(['remise' => $this->remiseAccordee($eleveId, $anneeScolaireId)]);
    }

    /**
     * Enregistre une dette antérieure au système (comptabilité reprise, élève
     * transféré). Si l'élève a déjà un dossier ouvert pour l'année active de
     * son école, la dette y est imputée sur-le-champ ; sinon elle attend
     * d'être reprise à l'ouverture du prochain dossier (cf. `dossier()`).
     */
    public function enregistrerDetteAnterieure(Eleve $eleve, int $montant, ?string $motif, ?int $accordePar): DetteAnterieure
    {
        if ($montant <= 0) {
            throw new RuntimeException('Le montant de la dette doit être supérieur à zéro.');
        }

        return $this->transaction(function () use ($eleve, $montant, $motif, $accordePar) {
            $dette = DetteAnterieure::create([
                'school_id' => $eleve->school_id,
                'eleve_id' => $eleve->id,
                'montant' => $montant,
                'motif' => $motif,
                'accorde_par' => $accordePar,
            ]);

            $anneeActive = AnneeScolaire::where('school_id', $eleve->school_id)->where('is_active', true)->first();
            $dossierOuvert = $anneeActive
                ? DossierScolarite::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $anneeActive->id)->first()
                : null;

            if ($dossierOuvert) {
                $dossierOuvert->increment('report_dette', $montant);
                $dette->update(['imputee_dossier_id' => $dossierOuvert->id]);
            }

            return $dette;
        });
    }

    /** Retire une dette non encore imputée — une fois reprise dans un dossier, elle ne se supprime plus isolément. */
    public function supprimerDetteAnterieure(DetteAnterieure $dette): void
    {
        if ($dette->imputee_dossier_id !== null) {
            throw new RuntimeException('Cette dette a déjà été imputée à un dossier ; elle ne peut plus être retirée isolément.');
        }

        $dette->delete();
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
            ->where(fn($q) => $q->whereNull('classe_id')->orWhere('classe_id', $eleve->classe_id))
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
            ->whereHas('anneeScolaire', fn($q) => $q->where('date_debut', '<', $annee->date_debut))
            ->avecTotaux()
            ->latest('id')
            ->first();

        return $precedent?->reste_a_payer ?? 0;
    }

    /**
     * Frais obligatoires applicables à une classe donnée : ceux sans classe
     * rattachée (portée école entière) plus ceux dont la classe figure dans
     * leur périmètre. `$classeId` nul (élève sans classe) n'écarte que les
     * frais circonscrits — un frais école entière s'applique quand même.
     *
     * @return Collection<int, FraisAnnexe>
     */
    private function fraisObligatoires(int $schoolId, int $anneeId, ?int $classeId = null)
    {
        return FraisAnnexe::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('obligatoire', true)
            ->where('is_active', true)
            ->where(function ($q) use ($classeId) {
                $q->whereDoesntHave('classes');

                if ($classeId !== null) {
                    $q->orWhereHas('classes', fn($q2) => $q2->where('classes.id', $classeId));
                }
            })
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
        $total = array_sum(array_map(static fn($l) => (int) $l['montant'], $lignes));

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
            'libelle' => 'Encaissement scolarité — reçu ' . $versement->numero_recu,
            'montant' => $versement->montant,
            'sens' => 'debit',
            'compte_comptable_id' => $this->compte(self::COMPTES_TRESORERIE[$versement->mode] ?? '571'),
        ]);

        foreach ($versement->lignes as $ligne) {
            EcritureComptable::create($commun + [
                'libelle' => $ligne->libelle . ' — reçu ' . $versement->numero_recu,
                'montant' => $ligne->montant,
                'sens' => 'credit',
                'compte_comptable_id' => $this->compte(
                    $ligne->affectation === 'frais_annexe'
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
            ->when($filtres['classe_id'] ?? null, fn($q, $classeId) => $q->where('classe_id', $classeId))
            ->with(['classe', 'tuteurs'])
            ->orderBy('nom_complet')
            ->get();

        $dossiers = DossierScolarite::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->avecTotaux()
            ->get()
            ->keyBy('eleve_id');

        // Projection commune aux élèves sans dossier, précalculée par classe
        // (un frais obligatoire peut être circonscrit à certaines classes)
        // plutôt que relue élève par élève.
        $fraisObligatoiresParClasse = [];
        foreach ($eleves->pluck('classe_id')->unique() as $classeId) {
            $fraisObligatoiresParClasse[$classeId ?? 0] = (int) $this->fraisObligatoires($schoolId, $anneeScolaireId, $classeId)->sum('montant');
        }

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
            ->map(function (Eleve $eleve) use ($dossiers, $annee, $fraisObligatoiresParClasse, $busParEleve) {
                $dossier = $dossiers->get($eleve->id);

                if ($dossier) {
                    $dossier->setRelation('eleve', $eleve);

                    return $dossier;
                }

                // Dossier projeté, non enregistré : il porte `id = null`, ce qui
                // signale à l'interface qu'il faut l'ouvrir avant d'encaisser.
                $fraisObligatoires = $fraisObligatoiresParClasse[$eleve->classe_id ?? 0] ?? 0;

                return $this->dossierProjete($eleve, $annee, $fraisObligatoires, $busParEleve->get($eleve->id, new EloquentCollection));
            })
            // Le statut se déduit des versements : il ne peut pas se filtrer en
            // SQL sans dupliquer le calcul des soldes dans la requête.
            ->when(
                $filtres['statut'] ?? null,
                fn($c, $statut) => $c->where('statut_paiement', $statut),
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
     * Liste des insolvables, un ou plusieurs établissements à la fois — c'est
     * ce qui change par rapport à `situation()` (une seule école, un statut
     * binaire) : ici le seuil est un pourcentage de la scolarité, propre à
     * chaque école (réglage `seuil_insolvabilite`), et le mode agrégé doit
     * pouvoir balayer tout le complexe en un seul écran.
     *
     * @param  list<int>  $schoolIds
     * @param  array{classe_id?: ?int}  $filtres
     * @return array{lignes: Collection, totaux: array{effectif: int, total_du: int, total_reste: int}}
     */
    public function insolvables(array $schoolIds, array $filtres = []): array
    {
        $classeId = $filtres['classe_id'] ?? null;
        $ecoles = School::whereIn('id', $schoolIds)->get()->keyBy('id');

        $candidats = collect();

        foreach ($schoolIds as $schoolId) {
            $annee = AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
            if (! $annee) {
                continue;
            }

            $seuilPourcentage = (float) Setting::get($schoolId, 'seuil_insolvabilite', 0);
            $situation = $this->situation($schoolId, $annee->id, ['classe_id' => $classeId]);

            foreach ($situation['dossiers'] as $dossier) {
                /*
                 * Dès qu'un échéancier existe, l'insolvabilité se juge sur ce qui
                 * est exigible AUJOURD'HUI et non sur le total de l'année : une
                 * famille à jour de sa première tranche n'a pas à figurer à côté
                 * de celle qui n'a rien versé. Sans échéancier, `retard()` rend
                 * le reste à payer et le comportement antérieur est conservé.
                 */
                $echeancier = $this->echeancier->pourDossier($dossier);

                $seuil = (int) round($dossier->montant_scolarite * $seuilPourcentage / 100);

                if ($echeancier['retard'] > $seuil) {
                    $candidats->push([
                        'dossier' => $dossier,
                        'school' => $ecoles->get($schoolId),
                        'seuil' => $seuil,
                        'echeancier' => $echeancier,
                    ]);
                }
            }
        }

        $eleveIds = $candidats->map(fn(array $c) => $c['dossier']->eleve->id)->filter()->unique()->values();
        $moratoires = Moratoire::whereIn('eleve_id', $eleveIds)->valides()->get()->keyBy('eleve_id');

        $lignes = $candidats
            ->map(function (array $candidat) use ($moratoires) {
                $dossier = $candidat['dossier'];
                $moratoire = $moratoires->get($dossier->eleve->id);

                return [
                    'eleve' => [
                        'id' => $dossier->eleve->id,
                        'matricule' => $dossier->eleve->matricule,
                        'nom_complet' => $dossier->eleve->nom_complet,
                        'classe' => $dossier->eleve->classe?->nom,
                    ],
                    'school' => ['id' => $candidat['school']->id, 'name' => $candidat['school']->name],
                    'seuil' => $candidat['seuil'],
                    'total_du' => $dossier->total_du,
                    'total_paye' => $dossier->total_paye,
                    'reste_a_payer' => $dossier->reste_a_payer,
                    // Ce qui motive réellement la relance quand l'école a
                    // découpé son année : le retard sur les échéances déjà
                    // passées, distinct du reste à payer sur l'année entière.
                    'echeancier_actif' => $candidat['echeancier']['actif'],
                    'du_a_ce_jour' => $candidat['echeancier']['du_a_ce_jour'],
                    'retard' => $candidat['echeancier']['retard'],
                    'tranches_en_retard' => collect($candidat['echeancier']['tranches'])
                        ->where('statut', 'en_retard')
                        ->map(fn(array $t) => [
                            'libelle' => $t['libelle'],
                            'date_echeance' => $t['date_echeance'],
                            'reste' => $t['reste'],
                        ])->values()->all(),
                    'rubriques' => $dossier->rubriques,
                    // Une précision de date, pas un statut à part : un moratoire
                    // valide n'exclut pas l'élève de la liste — la famille reste
                    // insolvable, elle a seulement un délai accordé et daté.
                    'moratoire' => $moratoire ? [
                        'date_expiration' => $moratoire->date_expiration->format('Y-m-d'),
                        'motif' => $moratoire->motif,
                    ] : null,
                ];
            })
            ->sortBy([['school.name', 'asc'], ['eleve.nom_complet', 'asc']])
            ->values();

        return [
            'lignes' => $lignes,
            'totaux' => [
                'effectif' => $lignes->count(),
                'total_du' => (int) $lignes->sum('total_du'),
                'total_reste' => (int) $lignes->sum('reste_a_payer'),
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
        $detteAnterieure = (int) DetteAnterieure::where('eleve_id', $eleve->id)->nonImputees()->sum('montant');

        $dossier = new DossierScolarite([
            'school_id' => $eleve->school_id,
            'annee_scolaire_id' => $annee->id,
            'eleve_id' => $eleve->id,
            'montant_scolarite' => $this->tarif($eleve, $annee),
            'remise' => $this->remiseAccordee($eleve->id, $annee->id),
            'report_dette' => $this->reliquatAnneePrecedente($eleve, $annee) + $detteAnterieure,
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
                    'libelle' => 'Annulation — ' . $ecriture->libelle,
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
