<?php

namespace App\Imports;

use App\Models\Personnel;
use App\Models\Remuneration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import de la feuille « Import » du modèle de rémunérations
 * (cf. RemunerationTemplateExport) : une ligne = une rémunération à cette
 * date d'effet, rapprochée de l'agent par son nom complet — la colonne Nom
 * est une liste déroulante côté Excel, alimentée par le personnel en poste
 * au moment du téléchargement du modèle, donc en principe déjà exacte.
 *
 * Comme pour une saisie directe (cf. RemunerationController::store()), une
 * seconde ligne à la même date d'effet remplace la première : c'est une
 * correction, pas un nouvel historique.
 */
class RemunerationImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    private const GAINS = [
        'salaire_base', 'prime_anciennete', 'prime_communication',
        'prime_transport', 'prime_recherche', 'prime_performance',
    ];

    private const COLONNES = [
        'nomcomplet' => 'nom_complet',
        'nom' => 'nom_complet',
        'dateeffet' => 'date_effet',
        'mode' => 'mode',
        'salairedebase' => 'salaire_base',
        'tauxhoraire' => 'taux_horaire',
        'primeanciennete' => 'prime_anciennete',
        'primecommunication' => 'prime_communication',
        'primetransport' => 'prime_transport',
        'primerecherche' => 'prime_recherche',
        'primeperformance' => 'prime_performance',
        'categorie' => 'categorie',
    ];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    /** @var array<string, int> nom saisi => nombre de lignes ignorées, faute de correspondance */
    public array $nomsNonRattaches = [];

    /** @var Collection<string, Personnel>|null nom complet normalisé => agent */
    private ?Collection $personnels = null;

    public function __construct(private readonly int $schoolId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);

            if ($ligne['nom_complet'] === null) {
                continue;
            }

            $personnel = $this->personnels()->get(self::cle($ligne['nom_complet']));

            if ($personnel === null) {
                $this->nomsNonRattaches[$ligne['nom_complet']] = ($this->nomsNonRattaches[$ligne['nom_complet']] ?? 0) + 1;

                continue;
            }

            $this->enregistrer($personnel, $ligne);
        }
    }

    public function rules(): array
    {
        return [
            '*.nom_complet' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nom_complet: ?string, date_effet: ?string, mode: ?string, salaire_base: ?int, taux_horaire: ?int, prime_anciennete: ?int, prime_communication: ?int, prime_transport: ?int, prime_recherche: ?int, prime_performance: ?int, categorie: ?string}
     */
    private function normaliser(array $data): array
    {
        $ligne = [];
        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[self::cle((string) $entete)] ?? null;
            $valeur = self::nettoyer($valeur);

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        return [
            'nom_complet' => isset($ligne['nom_complet']) ? self::texte($ligne['nom_complet']) : null,
            'date_effet' => self::date($ligne['date_effet'] ?? null),
            'mode' => self::mode($ligne['mode'] ?? null),
            'salaire_base' => self::entier($ligne['salaire_base'] ?? null),
            'taux_horaire' => self::entier($ligne['taux_horaire'] ?? null),
            'prime_anciennete' => self::entier($ligne['prime_anciennete'] ?? null),
            'prime_communication' => self::entier($ligne['prime_communication'] ?? null),
            'prime_transport' => self::entier($ligne['prime_transport'] ?? null),
            'prime_recherche' => self::entier($ligne['prime_recherche'] ?? null),
            'prime_performance' => self::entier($ligne['prime_performance'] ?? null),
            'categorie' => isset($ligne['categorie']) ? self::texte($ligne['categorie']) : null,
        ];
    }

    /** @param  array<string, mixed>  $ligne */
    private function enregistrer(Personnel $personnel, array $ligne): void
    {
        // Sans date d'effet, la rémunération n'a pas de clé d'unicité : la
        // ligne est ignorée plutôt que d'écraser silencieusement la plus
        // récente rémunération de l'agent.
        if ($ligne['date_effet'] === null) {
            return;
        }

        $horaire = ($ligne['mode'] ?? 'mensuel') === 'horaire';

        $remuneration = Remuneration::updateOrCreate(
            ['personnel_id' => $personnel->id, 'date_effet' => $ligne['date_effet']],
            [
                'school_id' => $personnel->school_id,
                'mode' => $horaire ? 'horaire' : 'mensuel',
                'taux_horaire' => $horaire ? ($ligne['taux_horaire'] ?? 0) : null,
                ...collect(self::GAINS)->mapWithKeys(
                    fn (string $champ) => [$champ => $horaire ? 0 : ($ligne[$champ] ?? 0)],
                ),
                'categorie' => $ligne['categorie'],
            ],
        );

        $remuneration->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;
    }

    /** @return Collection<string, Personnel> */
    private function personnels(): Collection
    {
        return $this->personnels ??= Personnel::where('school_id', $this->schoolId)
            ->get()
            ->keyBy(fn (Personnel $p) => self::cle($p->nom_complet));
    }

    private static function cle(?string $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(Str::ascii((string) $valeur))) ?? '';
    }

    private static function nettoyer(mixed $valeur): mixed
    {
        if (is_string($valeur)) {
            $valeur = trim($valeur);
        }

        return ($valeur === '' || $valeur === null) ? null : $valeur;
    }

    private static function texte(mixed $valeur): ?string
    {
        return self::nettoyer(preg_replace('/\s+/u', ' ', (string) $valeur));
    }

    private static function entier(mixed $valeur): ?int
    {
        return is_numeric($valeur) ? max(0, (int) round((float) $valeur)) : null;
    }

    private static function mode(mixed $valeur): ?string
    {
        return self::cle(self::texte($valeur)) === 'horaire' ? 'horaire' : 'mensuel';
    }

    private static function date(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valeur)->toDateString();
        }

        $texte = trim((string) $valeur);

        if (is_numeric($texte) && ! preg_match('/^\d{8}$/', $texte)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $texte))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['!Y-m-d', '!Ymd', '!d/m/Y', '!d-m-Y', '!Y/m/d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $texte)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
