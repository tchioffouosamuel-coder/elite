<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\BulletinPaie;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Models\Seance;
use App\Services\Paie\Bareme;
use App\Services\Paie\ResultatPaie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Arrêté de paie mensuel.
 *
 * Un bulletin passe par trois états. En **brouillon**, il se recalcule à
 * volonté : c'est la période de préparation, où l'on saisit les jours
 * travaillés et les déductions maison. **Arrêté**, il est figé et engage
 * l'établissement — c'est là que naissent les écritures comptables. **Payé**,
 * il porte la date et le mode du règlement, puis l'émargement de l'agent.
 *
 * Un bulletin arrêté ne se recalcule plus : les taux changent, et un document
 * remis à l'agent puis déclaré à la CNPS ne doit pas se mettre à dire autre
 * chose l'année suivante.
 */
class PaieService extends BaseService
{
    private const TYPE_DOCUMENT = 'bulletin_paie';

    /*
     * Charges de personnel, ventilées sur le triplet que présente l'état de
     * synthèse de l'établissement : salaires, CNPS, charges fiscales. Les
     * regrouper sur un compte unique interdirait de rapprocher l'application
     * du classeur, où les trois lignes se lisent séparément.
     */
    /**
     * Durée d'une heure pédagogique — un créneau de cours, pas une heure
     * d'horloge : c'est ainsi que l'établissement compte les vacations,
     * conformément à l'usage scolaire camerounais. Un créneau de 100 minutes
     * (deux périodes accolées) vaut deux heures, pas 1,67.
     */
    private const MINUTES_PAR_HEURE_PEDAGOGIQUE = 50;

    private const COMPTE_SALAIRES = '661';

    private const COMPTE_CNPS_CHARGE = '662';

    private const COMPTE_IMPOTS_CHARGE = '663';

    private const COMPTE_PERSONNEL = '421';

    private const COMPTE_CNPS = '431';

    private const COMPTE_IMPOTS = '441';

    /** Raff, njangi, prêts, absences : dus à l'école, pas à un organisme. */
    private const COMPTE_RETENUES_INTERNES = '471';

    private const COMPTES_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    public function __construct(
        private readonly Bareme $bareme,
        private readonly DocumentReferenceService $references,
        private readonly AvanceSalaireService $avances,
    ) {}

    /**
     * Prépare — ou recalcule — le bulletin d'un agent pour un mois donné.
     *
     * @param  array{jours_ouvrables?: int, jours_travailles?: int, deduction_raff?: int, deduction_njangi?: int, deduction_pret?: int, deduction_autre?: int}  $saisie
     */
    public function preparer(Personnel $personnel, int $annee, int $mois, array $saisie = []): BulletinPaie
    {
        $existant = BulletinPaie::where('personnel_id', $personnel->id)
            ->where('annee', $annee)->where('mois', $mois)->first();

        if ($existant && ! $existant->estModifiable()) {
            throw new RuntimeException(
                "Le bulletin de {$personnel->nom_complet} pour {$existant->periode_libelle} est déjà arrêté.",
            );
        }

        $remuneration = $this->remuneration($personnel, $annee, $mois);

        if (! $remuneration) {
            throw new RuntimeException("Aucune rémunération n'est définie pour {$personnel->nom_complet}.");
        }

        return $this->transaction(function () use ($personnel, $annee, $mois, $saisie, $remuneration, $existant) {
            $debut = Carbon::create($annee, $mois, 1)->startOfDay();

            /*
             * Deux régimes cohabitent dans le complexe. Au primaire, un salaire
             * mensuel négocié à la rentrée. Au technique, une vacation : seules
             * les heures enseignées sont dues, au taux du contrat. Le second
             * n'a pas de primes — il n'a qu'un volume et un taux.
             */
            $heures = isset($saisie['heures']) ? max(0, (int) $saisie['heures']) : null;

            if ($remuneration->estHoraire()) {
                /*
                 * Sans heure saisie à la main, le mois se reconstitue depuis
                 * ce que l'enseignant a lui-même déclaré tenu dans Ma journée
                 * — la validation de cours fait, créneau par créneau. La
                 * saisie manuelle garde le dernier mot quand elle existe : un
                 * rattrapage tenu hors emploi du temps, ou une correction,
                 * ne doivent pas attendre un pointage qui n'aura pas lieu.
                 */
                if ($heures === null) {
                    $heures = $this->heuresValidees($personnel, $annee, $mois);

                    // Zéro saisi à la main est une confirmation — l'agent n'a
                    // effectivement rien tenu. Zéro déduit de Ma journée, en
                    // revanche, ne veut rien dire par défaut : ni « rien
                    // tenu » ni « juste pas pointé » ne se déduisent l'un de
                    // l'autre, la question mérite une réponse explicite.
                    if ($heures === 0) {
                        throw new RuntimeException(
                            "{$personnel->nom_complet} est payé à l'heure : aucune séance effectuée n'est enregistrée pour ce mois dans Ma journée, et aucune heure n'a été saisie explicitement.",
                        );
                    }
                }

                $gains = ['salaire_base' => $heures * (int) $remuneration->taux_horaire];
            } else {
                $gains = [
                    'salaire_base' => $remuneration->salaire_base,
                    'prime_anciennete' => $remuneration->prime_anciennete,
                    'prime_communication' => $remuneration->prime_communication,
                    'prime_transport' => $remuneration->prime_transport,
                    'prime_recherche' => $remuneration->prime_recherche,
                    'prime_performance' => $remuneration->prime_performance,
                ];
            }

            /*
             * Un vacataire n'est pas salarié : ni IRPP, ni CNPS, ni aucune des
             * charges du barème. Le document remis n'est pas un bulletin de
             * paie mais un reçu de paiement pour les heures enseignées — d'où
             * un résultat directement construit plutôt que passé au barème,
             * qui ne connaît que des salariés mensuels.
             */
            $resultat = $remuneration->estHoraire()
                ? new ResultatPaie(
                    brut: $gains['salaire_base'],
                    baseTaxable: 0,
                    chargesSalariales: 0,
                    chargesPatronales: 0,
                    gains: [],
                    retenues: [],
                )
                : $this->bareme->calculer($gains);

            $joursOuvrables = (int) ($saisie['jours_ouvrables'] ?? 22);
            $joursTravailles = (int) ($saisie['jours_travailles'] ?? $joursOuvrables);

            $deductions = [
                /*
                 * Une vacation ne se proratise pas : les heures non faites ne
                 * sont simplement pas payées. Retenir en plus reviendrait à
                 * les compter deux fois.
                 */
                'deduction_absences' => $remuneration->estHoraire()
                    ? 0
                    : $this->deductionAbsences($resultat->brut, $joursOuvrables, $joursTravailles),
                'deduction_raff' => (int) ($saisie['deduction_raff'] ?? 0),
                'deduction_njangi' => (int) ($saisie['deduction_njangi'] ?? 0),
                /*
                 * L'échéancier de l'avance commande la retenue : la mensualité
                 * accordée, bornée par ce qui reste dû. La saisie garde le
                 * dernier mot — un mois de trésorerie difficile se négocie —
                 * mais elle part de ce que le dossier prévoit, et non d'un
                 * montant recopié de mois en mois.
                 */
                'deduction_pret' => (int) ($saisie['deduction_pret'] ?? $this->avances->mensualiteDue($personnel->id)),
                'deduction_autre' => (int) ($saisie['deduction_autre'] ?? 0),
            ];

            $bulletin = $existant ?? new BulletinPaie([
                'school_id' => $personnel->school_id,
                'personnel_id' => $personnel->id,
                'annee' => $annee,
                'mois' => $mois,
                'numero' => $this->numero($personnel->school),
            ]);

            $bulletin->fill([
                // Sans elle, les écritures de paie sortent du compte de
                // résultat dès qu'on le filtre par année scolaire — et la
                // masse salariale disparaît des charges.
                'annee_scolaire_id' => $this->anneeScolaire($personnel->school_id, $debut),
                'periode_debut' => $debut->toDateString(),
                'periode_fin' => $debut->copy()->endOfMonth()->toDateString(),
                'jours_ouvrables' => $joursOuvrables,
                'jours_travailles' => $joursTravailles,
                'heures' => $heures,
                'taux_horaire' => $remuneration->estHoraire() ? $remuneration->taux_horaire : null,
                'salaire_brut' => $resultat->brut,
                'net_taxable' => $resultat->baseTaxable,
                'charges_salariales' => $resultat->chargesSalariales,
                'charges_patronales' => $resultat->chargesPatronales,
                // Trace du barème : un bulletin doit rester relisible quand le
                // réglage aura changé.
                'bareme' => $this->bareme->libelle(),
                'charges_salariales_a_charge_employeur' => $resultat->chargesSalarialesSupporteesParEmployeur,
                ...$deductions,
                // Le net ne descend pas sous zéro : une retenue supérieure au
                // net se reporte, elle ne transforme pas la paie en créance.
                'net_a_payer' => max(0, $resultat->netAvantDeductions() - array_sum($deductions)),
                'statut' => 'brouillon',
            ])->save();

            $this->enregistrerLignes($bulletin, $resultat);

            return $bulletin->load('lignes');
        });
    }

    /**
     * Prépare la paie de tout le personnel en poste. Les agents sans
     * rémunération définie sont signalés plutôt qu'ignorés : c'est presque
     * toujours un oubli de saisie, pas une intention.
     *
     * @return array{bulletins: Collection<int, BulletinPaie>, ignores: list<string>}
     */
    public function preparerLot(int|array $schoolId, int $annee, int $mois, array $saisie = []): array
    {
        $bulletins = collect();
        $ignores = [];

        $personnels = Personnel::forSchool($schoolId)->where('statut', 'actif')->orderBy('nom_complet')->get();

        foreach ($personnels as $personnel) {
            try {
                $bulletins->push($this->preparer($personnel, $annee, $mois, $saisie));
            } catch (RuntimeException $e) {
                /*
                 * Un vacataire n'échoue ici que pour une raison : ses heures du
                 * mois ne sont pas dans la saisie globale, propre à tout le
                 * personnel mensuel. Le distinguer d'un agent sans rémunération
                 * permet à l'écran de proposer la bonne action — un champ
                 * heures, pas un renvoi vers la fiche de rémunération.
                 */
                $remuneration = $this->remuneration($personnel, $annee, $mois);

                $ignores[] = [
                    'personnel_id' => $personnel->id,
                    'nom_complet' => $personnel->nom_complet,
                    'motif' => $remuneration?->estHoraire() ? 'heures_requises' : 'sans_remuneration',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['bulletins' => $bulletins, 'ignores' => $ignores];
    }

    /**
     * Année scolaire couvrant le mois de paie : celle dont la période
     * l'englobe, à défaut l'année active. Un salaire de septembre appartient à
     * l'exercice qui commence, pas à celui qui vient de se clore.
     */
    private function anneeScolaire(int $schoolId, Carbon $mois): ?int
    {
        return AnneeScolaire::where('school_id', $schoolId)
            ->whereDate('date_debut', '<=', $mois->copy()->endOfMonth())
            ->whereDate('date_fin', '>=', $mois)
            ->value('id')
            ?? AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->value('id');
    }

    /**
     * Heures payables au vacataire pour le mois : ses séances déclarées
     * effectuées dans Ma journée, converties en heures pédagogiques de 50
     * minutes. Une séance annulée ou restée à l'état prévu ne compte pas —
     * seule la validation de cours fait, pas l'emploi du temps théorique,
     * engage le paiement.
     */
    private function heuresValidees(Personnel $personnel, int $annee, int $mois): int
    {
        $minutes = Seance::whereHas('classeMatiere', fn ($q) => $q->where('personnel_id', $personnel->id))
            ->where('statut', 'effectuee')
            ->whereYear('date_seance', $annee)
            ->whereMonth('date_seance', $mois)
            ->get()
            ->sum(fn (Seance $s) => $s->dureeHeures() * 60);

        return (int) round($minutes / self::MINUTES_PAR_HEURE_PEDAGOGIQUE);
    }

    /** Rémunération en vigueur au mois considéré. */
    private function remuneration(Personnel $personnel, int $annee, int $mois): ?Remuneration
    {
        return Remuneration::where('personnel_id', $personnel->id)
            ->whereDate('date_effet', '<=', Carbon::create($annee, $mois, 1)->endOfMonth())
            ->orderByDesc('date_effet')
            ->first();
    }

    /** Absence retenue au prorata des jours non travaillés. */
    private function deductionAbsences(int $brut, int $joursOuvrables, int $joursTravailles): int
    {
        if ($joursOuvrables <= 0 || $joursTravailles >= $joursOuvrables) {
            return 0;
        }

        return (int) round($brut * ($joursOuvrables - max(0, $joursTravailles)) / $joursOuvrables);
    }

    /**
     * Les lignes sont réécrites à chaque recalcul du brouillon, et conservées
     * telles quelles une fois le bulletin arrêté : c'est ce qui permet de le
     * rééditer à l'identique des années plus tard.
     */
    private function enregistrerLignes(BulletinPaie $bulletin, ResultatPaie $resultat): void
    {
        $bulletin->lignes()->delete();

        // Le vacataire n'a qu'une ligne : les heures du mois à son taux. Les
        // six libellés de salaire/primes et les retenues légales ne relèvent
        // que du salarié mensuel — $resultat les porte vides pour lui.
        if ($bulletin->taux_horaire !== null) {
            $bulletin->lignes()->create([
                'ordre' => 1,
                'type' => 'gain',
                'libelle' => sprintf('Vacation horaire (%d h × %s F CFA)', $bulletin->heures, number_format($bulletin->taux_horaire, 0, ',', ' ')),
                'libelle_en' => sprintf('Hourly vacation (%d h × %s F CFA)', $bulletin->heures, number_format($bulletin->taux_horaire, 0, ',', ' ')),
                'montant_salarial' => $resultat->brut,
            ]);

            return;
        }

        $ordre = 1;

        $libelles = [
            'salaire_base' => ['Salaire de base', 'Basic salary'],
            'prime_anciennete' => ['Ancienneté', 'Longevity'],
            'prime_communication' => ['Communication', 'Communication'],
            'prime_transport' => ['Prime de transport', 'Transport bonus'],
            'prime_recherche' => ['Recherche & leçon', 'Research & lesson'],
            'prime_performance' => ['Prime de performance', 'Performance bonus'],
        ];

        foreach ($libelles as $champ => [$fr, $en]) {
            $bulletin->lignes()->create([
                'ordre' => $ordre++,
                'type' => 'gain',
                'libelle' => $fr,
                'libelle_en' => $en,
                'montant_salarial' => $resultat->gains[$champ] ?? 0,
            ]);
        }

        foreach ($resultat->retenues as $retenue) {
            $bulletin->lignes()->create([
                'ordre' => $ordre++,
                'type' => 'retenue',
                'libelle' => $retenue['libelle'],
                'libelle_en' => $retenue['libelle_en'],
                'base' => $retenue['base'],
                'taux_salarial' => $retenue['taux_salarial'],
                'taux_patronal' => $retenue['taux_patronal'],
                'montant_salarial' => $retenue['montant_salarial'],
                'montant_patronal' => $retenue['montant_patronal'],
            ]);
        }
    }

    /**
     * Arrête le bulletin : il devient intangible et rejoint la comptabilité.
     */
    public function arreter(BulletinPaie $bulletin, ?int $validePar = null): BulletinPaie
    {
        if (! $bulletin->estModifiable()) {
            throw new RuntimeException('Ce bulletin est déjà arrêté.');
        }

        return $this->transaction(function () use ($bulletin, $validePar) {
            /*
             * La retenue devient un remboursement au registre des avances :
             * c'est l'arrêté qui l'engage, pas le brouillon, qui se recalcule
             * encore. Si l'agent doit moins que la retenue annoncée, seul le
             * dû est imputé et le bulletin est ramené à ce montant — sans quoi
             * le net versé et le solde de l'avance se contrediraient.
             *
             * L'imputation précède l'écriture : le journal doit porter la
             * retenue réellement pratiquée, pas celle qui était proposée.
             */
            $impute = $this->avances->imputerSurPaie(
                $bulletin->personnel_id,
                $bulletin->deduction_pret,
                $bulletin->periode_fin->toDateString(),
                'Retenue sur salaire — '.$bulletin->numero,
            );

            if ($impute !== $bulletin->deduction_pret) {
                $bulletin->update([
                    'deduction_pret' => $impute,
                    // Ce qui n'a pas pu être retenu revient à l'agent.
                    'net_a_payer' => $bulletin->net_a_payer + ($bulletin->deduction_pret - $impute),
                ]);
                $bulletin->refresh();
            }

            $bulletin->update(['statut' => 'valide', 'valide_par' => $validePar]);
            $this->comptabiliser($bulletin);

            return $bulletin->fresh();
        });
    }

    /** Enregistre le règlement effectif du salaire. */
    public function payer(BulletinPaie $bulletin, string $mode, ?string $date = null): BulletinPaie
    {
        if ($bulletin->statut === 'brouillon') {
            throw new RuntimeException('Un bulletin doit être arrêté avant d\'être payé.');
        }

        return $this->transaction(function () use ($bulletin, $mode, $date) {
            $bulletin->update([
                'statut' => 'paye',
                'mode_paiement' => $mode,
                'date_paiement' => $date ?? Carbon::today()->toDateString(),
            ]);

            // Décaissement : la dette envers l'agent s'éteint, la trésorerie baisse.
            $this->ecrire($bulletin, 'debit', self::COMPTE_PERSONNEL, $bulletin->net_a_payer, 'Règlement du salaire');
            $this->ecrire(
                $bulletin, 'credit',
                self::COMPTES_TRESORERIE[$mode] ?? '571',
                $bulletin->net_a_payer, 'Règlement du salaire',
            );

            return $bulletin->fresh();
        });
    }

    /**
     * Émargement : l'agent atteste avoir perçu son salaire. C'est la pièce que
     * l'établissement conserve, l'équivalent signé du registre de paie.
     */
    public function emarger(BulletinPaie $bulletin, ?string $reference = null): BulletinPaie
    {
        if ($bulletin->statut !== 'paye') {
            throw new RuntimeException("Le salaire doit être réglé avant d'être émargé.");
        }

        $bulletin->update(['emarge_le' => now(), 'emargement_reference' => $reference]);

        return $bulletin->fresh();
    }

    /**
     * Charge de personnel à l'arrêté : le brut et les charges patronales pèsent
     * sur le résultat, la dette se répartit entre l'agent, la CNPS et le fisc.
     */
    /**
     * Journal de paie, ventilé comme l'état de synthèse le présente : le brut
     * en 661, la CNPS en 662, les impôts et taxes en 663.
     *
     * Ce qui est porté en charge dépend de qui supporte la part salariale.
     * Dans les registres de l'établissement, l'agent perçoit son montant
     * négocié entier et l'école absorbe cette part : elle rejoint alors les
     * charges. Sous le barème légal, elle est retenue sur le net et ne
     * constitue qu'une dette envers l'organisme, jamais une charge de plus.
     *
     * Dans les deux cas, en contrepartie : le net dû à l'agent, la CNPS et
     * l'État à reverser, et les retenues internes qui restent en caisse.
     */
    private function comptabiliser(BulletinPaie $bulletin): void
    {
        $cotisations = $bulletin->lignes()->where('type', 'retenue')->get();
        $estCnps = fn ($ligne) => str_contains($ligne->libelle, 'CNPS');

        $part = fn ($lignes, string $colonne) => (int) $lignes->sum($colonne);

        $cnpsSalarial = $part($cotisations->filter($estCnps), 'montant_salarial');
        $cnpsPatronal = $part($cotisations->filter($estCnps), 'montant_patronal');
        $impotsSalarial = $part($cotisations->reject($estCnps), 'montant_salarial');
        $impotsPatronal = $part($cotisations->reject($estCnps), 'montant_patronal');

        $supportee = (bool) $bulletin->charges_salariales_a_charge_employeur;
        $cnpsCharge = $cnpsPatronal + ($supportee ? $cnpsSalarial : 0);
        $impotsCharge = $impotsPatronal + ($supportee ? $impotsSalarial : 0);

        $this->ecrire($bulletin, 'debit', self::COMPTE_SALAIRES, $bulletin->salaire_brut, 'Salaire brut');
        $this->ecrire($bulletin, 'debit', self::COMPTE_CNPS_CHARGE, $cnpsCharge, 'Cotisations CNPS');
        $this->ecrire($bulletin, 'debit', self::COMPTE_IMPOTS_CHARGE, $impotsCharge, 'Impôts et taxes sur salaire');

        $this->ecrire($bulletin, 'credit', self::COMPTE_PERSONNEL, $bulletin->net_a_payer, 'Net à payer');
        $this->ecrire($bulletin, 'credit', self::COMPTE_CNPS, $cnpsSalarial + $cnpsPatronal, 'CNPS à reverser');
        $this->ecrire($bulletin, 'credit', self::COMPTE_IMPOTS, $impotsSalarial + $impotsPatronal, 'Impôts et taxes à reverser');

        /*
         * Raff, njangi, prêt, absences : ces retenues ne partent à aucun
         * organisme, elles restent dans la caisse de l'école. Sans cette
         * écriture, le journal annoncerait un net supérieur au virement.
         */
        $this->ecrire($bulletin, 'credit', self::COMPTE_RETENUES_INTERNES, $bulletin->total_deductions, 'Retenues internes sur salaire');
    }

    private function ecrire(BulletinPaie $bulletin, string $sens, string $codeCompte, int $montant, string $libelle): void
    {
        if ($montant <= 0) {
            return;
        }

        EcritureComptable::create([
            'school_id' => $bulletin->school_id,
            'annee_scolaire_id' => $bulletin->annee_scolaire_id,
            'date_ecriture' => $bulletin->periode_fin,
            'libelle' => $libelle.' — '.$bulletin->numero,
            'montant' => $montant,
            'sens' => $sens,
            'compte_comptable_id' => CompteComptable::where('code', $codeCompte)->value('id'),
            'origine_type' => $bulletin->getMorphClass(),
            'origine_id' => $bulletin->id,
        ]);
    }

    private function numero(School $school): string
    {
        $reference = $this->references->attribuer($school->id, self::TYPE_DOCUMENT);

        return sprintf('BP-%s-%s', $school->code, str_pad((string) $reference->numero, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Masse salariale d'un mois : ce que la paie coûte réellement, part
     * patronale comprise.
     *
     * @return array{bulletins: Collection<int, BulletinPaie>, totaux: array<string, int>}
     */
    public function masseSalariale(int|array $schoolId, int $annee, int $mois): array
    {
        $bulletins = BulletinPaie::forSchool($schoolId)
            ->where('annee', $annee)->where('mois', $mois)
            ->with('personnel.fonctionReference')
            ->get();

        $arretes = $bulletins->whereIn('statut', ['valide', 'paye']);

        return [
            'bulletins' => $bulletins,
            'totaux' => [
                'effectif' => $bulletins->count(),
                'brut' => (int) $arretes->sum('salaire_brut'),
                'charges_salariales' => (int) $arretes->sum('charges_salariales'),
                'charges_patronales' => (int) $arretes->sum('charges_patronales'),
                'net_a_payer' => (int) $arretes->sum('net_a_payer'),
                'cout_employeur' => (int) $arretes->sum(fn (BulletinPaie $b) => $b->cout_employeur),
                'regles' => $bulletins->where('statut', 'paye')->count(),
                'emarges' => $bulletins->whereNotNull('emarge_le')->count(),
            ],
        ];
    }
}
