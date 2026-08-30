<?php

namespace App\Imports;

use App\Models\CompteComptable;
use App\Services\DepenseService;
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
use Throwable;

/**
 * Import en masse de dépenses courantes (caisse ou revenu personnel).
 *
 * Volontairement hors périmètre : les dépenses liées à un véhicule ou
 * imputées sur le budget d'un agent, qui désignent chacune une entité
 * précise (quel véhicule, quel budget) et restent à leur place dans leurs
 * écrans dédiés plutôt qu'en saisie de masse.
 *
 * Chaque ligne crée une dépense — il n'y a pas de clé naturelle pour
 * rapprocher une ligne réimportée de la précédente (contrairement au
 * personnel ou à une rémunération) : réimporter le même fichier double
 * chaque dépense, comme resaisir deux fois le même bordereau papier.
 */
class DepenseImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    private const COLONNES = [
        'date' => 'date_depense',
        'datedepense' => 'date_depense',
        'libelle' => 'libelle',
        'montant' => 'montant',
        'mode' => 'mode',
        'beneficiaire' => 'beneficiaire',
        'nfacture' => 'reference_facture',
        'referencefacture' => 'reference_facture',
        'responsable' => 'responsable',
        'comptecomptable' => 'compte',
        'compte' => 'compte',
        'source' => 'source',
        'statut' => 'statut',
    ];

    public int $importedCount = 0;

    /** @var array<string, int> libellé de dépense => nombre de lignes dont le compte comptable saisi n'a pas été reconnu */
    public array $comptesNonRattaches = [];

    /** @var array<int, string> libellé des lignes en échec métier (budget insuffisant, etc.) — distinct de failures(), qui ne couvre que la validation de forme */
    public array $erreurs = [];

    /** @var Collection<string, CompteComptable>|null code/libellé normalisé => compte */
    private ?Collection $comptes = null;

    public function __construct(private readonly int $schoolId, private readonly ?int $saisiPar = null) {}

    public function collection(Collection $rows): void
    {
        $service = app(DepenseService::class);

        foreach ($rows as $row) {
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);

            if ($ligne['libelle'] === null || $ligne['montant'] === null) {
                continue;
            }

            [$compteId, $compteBrut] = $this->compteId($ligne['compte']);

            if ($compteBrut !== null && $compteId === null) {
                $this->comptesNonRattaches[$ligne['libelle']] = ($this->comptesNonRattaches[$ligne['libelle']] ?? 0) + 1;
            }

            try {
                $service->enregistrer($this->schoolId, [
                    'libelle' => $ligne['libelle'],
                    'montant' => $ligne['montant'],
                    'date_depense' => $ligne['date_depense'],
                    'compte_comptable_id' => $compteId,
                    'mode' => $ligne['mode'] ?? 'especes',
                    'beneficiaire' => $ligne['beneficiaire'],
                    'reference_facture' => $ligne['reference_facture'],
                    'responsable' => $ligne['responsable'],
                    'source' => $ligne['source'] ?? 'caisse',
                    'statut' => $ligne['statut'] ?? 'payee',
                ], $this->saisiPar);

                $this->importedCount++;
            } catch (Throwable $e) {
                $this->erreurs[] = "{$ligne['libelle']} : {$e->getMessage()}";
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.libelle' => ['nullable', 'string'],
            '*.montant' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{libelle: ?string, montant: ?int, date_depense: ?string, mode: ?string, beneficiaire: ?string, reference_facture: ?string, responsable: ?string, compte: ?string, source: ?string, statut: ?string}
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
            'libelle' => isset($ligne['libelle']) ? self::texte($ligne['libelle']) : null,
            'montant' => isset($ligne['montant']) && is_numeric($ligne['montant']) ? max(0, (int) round((float) $ligne['montant'])) : null,
            'date_depense' => self::date($ligne['date_depense'] ?? null),
            'mode' => self::mode($ligne['mode'] ?? null),
            'beneficiaire' => isset($ligne['beneficiaire']) ? self::texte($ligne['beneficiaire']) : null,
            'reference_facture' => isset($ligne['reference_facture']) ? self::texte($ligne['reference_facture']) : null,
            'responsable' => isset($ligne['responsable']) ? self::texte($ligne['responsable']) : null,
            'compte' => isset($ligne['compte']) ? self::texte($ligne['compte']) : null,
            'source' => self::source($ligne['source'] ?? null),
            'statut' => self::statut($ligne['statut'] ?? null),
        ];
    }

    /**
     * Rapproche un compte saisi (code ou libellé) au plan comptable de
     * l'établissement. Un compte non reconnu n'est pas une erreur : la ligne
     * retombe sur le compte par défaut, exactement comme la saisie manuelle
     * sans compte choisi (cf. DepenseService::COMPTE_PAR_DEFAUT).
     *
     * @return array{0: ?int, 1: ?string} identifiant résolu (ou null), valeur brute saisie (ou null si la colonne était vide)
     */
    private function compteId(?string $saisie): array
    {
        if ($saisie === null) {
            return [null, null];
        }

        return [$this->comptes()->get(self::cle($saisie)), $saisie];
    }

    /** @return Collection<string, int> code/libellé normalisé => id de compte */
    private function comptes(): Collection
    {
        if ($this->comptes !== null) {
            return $this->comptes;
        }

        $this->comptes = collect();

        foreach (CompteComptable::where('is_active', true)->get(['id', 'code', 'libelle']) as $compte) {
            foreach ([$compte->code, $compte->libelle] as $libelle) {
                $cle = self::cle($libelle);

                if ($cle !== '' && ! $this->comptes->has($cle)) {
                    $this->comptes->put($cle, $compte->id);
                }
            }
        }

        return $this->comptes;
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

    private static function mode(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return match (true) {
            str_starts_with($cle, 'especes'), str_starts_with($cle, 'cash') => 'especes',
            str_starts_with($cle, 'mobilemoney'), str_starts_with($cle, 'momo') => 'mobile_money',
            str_starts_with($cle, 'virement') => 'virement',
            str_starts_with($cle, 'cheque') => 'cheque',
            str_starts_with($cle, 'depotbancaire'), str_starts_with($cle, 'banque') => 'depot_bancaire',
            default => null,
        };
    }

    private static function source(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return str_starts_with($cle, 'revenupersonnel') ? 'revenu_personnel' : null;
    }

    private static function statut(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return str_starts_with($cle, 'engage') ? 'engagee' : null;
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
            } catch (Throwable) {
                return null;
            }
        }

        foreach (['!Y-m-d', '!Ymd', '!d/m/Y', '!d-m-Y', '!Y/m/d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $texte)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
