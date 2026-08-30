<?php

namespace App\Imports;

use App\Models\BusArret;
use App\Models\BusTrajet;
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
 * Colonnes attendues (en-têtes insensibles à la casse) : trajet, nom,
 * lieu_dit, ordre, heure_passage.
 *
 * ToCollection plutôt que ToModel : chaque ligne doit résoudre un trajet par
 * nom (les arrêts n'ont pas de `school_id` propre, cf. RegistreSync) et une
 * ligne dont le trajet est introuvable doit être comptée à part plutôt que
 * silencieusement rejetée comme un simple échec de validation.
 */
class BusArretImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    /** @var array<string, int> libellé de trajet non résolu => nombre de lignes */
    public array $trajetsIntrouvables = [];

    /** @var array<string, int>|null clé normalisée du nom de trajet => id */
    private ?array $trajets = null;

    /** @var array<int, int> trajet_id => dernier ordre attribué pendant cet import */
    private array $dernierOrdre = [];

    public function __construct(private readonly int $schoolId)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $row->all();
            $libelle = trim((string) ($ligne['trajet'] ?? ''));
            $trajetId = $this->trajetId($libelle);

            if ($trajetId === null) {
                if ($libelle !== '') {
                    $this->trajetsIntrouvables[$libelle] = ($this->trajetsIntrouvables[$libelle] ?? 0) + 1;
                }

                continue;
            }

            $ordre = self::entier($ligne['ordre'] ?? null) ?? $this->prochainOrdre($trajetId);

            BusArret::create([
                'trajet_id' => $trajetId,
                'nom' => $ligne['nom'],
                'lieu_dit' => $ligne['lieu_dit'] ?? null,
                'ordre' => $ordre,
                'heure_passage' => self::heure($ligne['heure_passage'] ?? null),
            ]);

            $this->dernierOrdre[$trajetId] = $ordre;
            $this->importedCount++;
        }
    }

    public function rules(): array
    {
        return [
            'trajet' => ['required', 'string'],
            'nom' => ['required', 'string'],
        ];
    }

    private function prochainOrdre(int $trajetId): int
    {
        if (! isset($this->dernierOrdre[$trajetId])) {
            $this->dernierOrdre[$trajetId] = (int) (BusArret::where('trajet_id', $trajetId)->max('ordre') ?? 0);
        }

        return ++$this->dernierOrdre[$trajetId];
    }

    private function trajetId(string $libelle): ?int
    {
        if ($libelle === '') {
            return null;
        }

        return $this->trajets()[self::cle($libelle)] ?? null;
    }

    /** @return array<string, int> */
    private function trajets(): array
    {
        if ($this->trajets !== null) {
            return $this->trajets;
        }

        $this->trajets = [];

        foreach (BusTrajet::where('school_id', $this->schoolId)->get(['id', 'nom']) as $trajet) {
            $this->trajets[self::cle($trajet->nom)] = $trajet->id;
        }

        return $this->trajets;
    }

    /** Clé de rapprochement insensible à la casse, aux accents et aux espaces. */
    private static function cle(string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii($libelle))) ?? '';
    }

    private static function entier(mixed $valeur): ?int
    {
        return ($valeur === null || $valeur === '') ? null : (int) $valeur;
    }

    /** Accepte une heure Excel (fraction de jour), une date/heure ou un texte « H:i ». */
    private static function heure(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('H:i');
        }

        if (is_numeric($valeur)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valeur)->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }

        $texte = trim((string) $valeur);

        return preg_match('/^(\d{1,2}):(\d{2})/', $texte, $m) === 1
            ? sprintf('%02d:%02d', (int) $m[1], (int) $m[2])
            : null;
    }
}
