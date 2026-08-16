<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\BulletinPaie;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Services\Paie\BaremePaie;
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

    /** Charges de personnel, dettes envers l'agent et les organismes. */
    private const COMPTE_SALAIRES = '661';

    private const COMPTE_CHARGES_SOCIALES = '664';

    private const COMPTE_PERSONNEL = '421';

    private const COMPTE_CNPS = '431';

    private const COMPTE_IMPOTS = '441';

    private const COMPTES_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    public function __construct(
        private readonly BaremePaie $bareme,
        private readonly DocumentReferenceService $references,
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
            $resultat = $this->bareme->calculer([
                'salaire_base' => $remuneration->salaire_base,
                'prime_anciennete' => $remuneration->prime_anciennete,
                'prime_communication' => $remuneration->prime_communication,
                'prime_transport' => $remuneration->prime_transport,
                'prime_recherche' => $remuneration->prime_recherche,
                'prime_performance' => $remuneration->prime_performance,
            ]);

            $joursOuvrables = (int) ($saisie['jours_ouvrables'] ?? 22);
            $joursTravailles = (int) ($saisie['jours_travailles'] ?? $joursOuvrables);

            $deductions = [
                'deduction_absences' => $this->deductionAbsences($resultat->brut, $joursOuvrables, $joursTravailles),
                'deduction_raff' => (int) ($saisie['deduction_raff'] ?? 0),
                'deduction_njangi' => (int) ($saisie['deduction_njangi'] ?? 0),
                'deduction_pret' => (int) ($saisie['deduction_pret'] ?? 0),
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
                'salaire_brut' => $resultat->brut,
                'net_taxable' => $resultat->baseTaxable,
                'charges_salariales' => $resultat->chargesSalariales,
                'charges_patronales' => $resultat->chargesPatronales,
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
    public function preparerLot(int $schoolId, int $annee, int $mois, array $saisie = []): array
    {
        $bulletins = collect();
        $ignores = [];

        $personnels = Personnel::forSchool($schoolId)->where('statut', 'actif')->orderBy('nom_complet')->get();

        foreach ($personnels as $personnel) {
            try {
                $bulletins->push($this->preparer($personnel, $annee, $mois, $saisie));
            } catch (RuntimeException $e) {
                $ignores[] = $personnel->nom_complet.' — '.$e->getMessage();
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
    private function enregistrerLignes(BulletinPaie $bulletin, $resultat): void
    {
        $bulletin->lignes()->delete();
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
    private function comptabiliser(BulletinPaie $bulletin): void
    {
        $cotisations = $bulletin->lignes()->where('type', 'retenue')->get();
        $impots = (int) $cotisations->filter(
            fn ($l) => str_contains($l->libelle, 'IRPP') || str_contains($l->libelle, 'Taxe') || str_contains($l->libelle, 'Foncier'),
        )->sum('montant_salarial');
        $cnpsSalarie = $bulletin->charges_salariales - $impots;

        $this->ecrire($bulletin, 'debit', self::COMPTE_SALAIRES, $bulletin->salaire_brut, 'Salaire brut');
        $this->ecrire($bulletin, 'debit', self::COMPTE_CHARGES_SOCIALES, $bulletin->charges_patronales, 'Charges patronales');

        $this->ecrire($bulletin, 'credit', self::COMPTE_PERSONNEL, $bulletin->net_a_payer, 'Net à payer');
        $this->ecrire($bulletin, 'credit', self::COMPTE_CNPS, $cnpsSalarie + $bulletin->charges_patronales, 'Cotisations CNPS');
        $this->ecrire($bulletin, 'credit', self::COMPTE_IMPOTS, $impots, 'Impôts retenus à la source');
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
    public function masseSalariale(int $schoolId, int $annee, int $mois): array
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
