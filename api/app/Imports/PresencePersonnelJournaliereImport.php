<?php

namespace App\Imports;

use App\Models\Personnel;
use App\Models\PresencePersonnelJournaliere;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PresencePersonnelJournaliereImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    private const COLONNES = [
        'id' => 'id',
        'date' => 'date_presence',
        'datepresence' => 'date_presence',
        'matricule' => 'matricule',
        'nom' => 'nom_complet',
        'nomprenom' => 'nom_complet',
        'nomcomplet' => 'nom_complet',
        'heurearrivee' => 'heure_arrivee',
        'heuredarrivee' => 'heure_arrivee',
        'arrivee' => 'heure_arrivee',
        'heuredepart' => 'heure_depart',
        'heurededepart' => 'heure_depart',
        'depart' => 'heure_depart',
    ];

    public function __construct(
        private readonly int $schoolId,
        private readonly ?string $dateImposee = null,
    ) {}

    public static function enTetes(): array
    {
        return ['ID', 'Date', 'Matricule', 'Nom', "Heure d'arrivée", 'Heure de départ'];
    }

    public function prepareForValidation(array $data, int $index): array
    {
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[self::cle($entete)] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            if ($cle !== null && $valeur !== '') {
                $ligne[$cle] = $valeur;
            }
        }

        if ($this->dateImposee !== null) {
            $ligne['date_presence'] = $this->dateImposee;
        }

        if (isset($ligne['date_presence'])) {
            $ligne['date_presence'] = $this->date($ligne['date_presence']);
        }
        foreach (['heure_arrivee', 'heure_depart'] as $cle) {
            if (isset($ligne[$cle])) {
                $ligne[$cle] = $this->heure($ligne[$cle]);
            }
        }

        return $ligne;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $row instanceof Collection ? $row->all() : $row;
            $personnel = $this->personnel($ligne);

            if (! $personnel) {
                continue;
            }

            $presence = PresencePersonnelJournaliere::updateOrCreate(
                [
                    'school_id' => $this->schoolId,
                    'personnel_id' => $personnel->id,
                    'date_presence' => $ligne['date_presence'],
                ],
                [
                    'heure_arrivee' => $ligne['heure_arrivee'] ?? null,
                    'heure_depart' => $ligne['heure_depart'] ?? null,
                    'source' => 'import_ocr',
                ],
            );

            $presence->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;
        }
    }

    public function rules(): array
    {
        return [
            'date_presence' => ['required', 'date'],
            'heure_arrivee' => ['nullable', 'date_format:H:i'],
            'heure_depart' => ['nullable', 'date_format:H:i', 'after:heure_arrivee'],
        ];
    }

    private function personnel(array $ligne): ?Personnel
    {
        $query = Personnel::forSchool($this->schoolId);

        if (! empty($ligne['matricule'])) {
            $personnel = (clone $query)->where('matricule', $ligne['matricule'])->first();
            if ($personnel) {
                return $personnel;
            }
        }

        if (! empty($ligne['nom_complet'])) {
            $cle = self::cle($ligne['nom_complet']);

            return $query->get()->first(fn (Personnel $p) => self::cle($p->nom_complet) === $cle);
        }

        return null;
    }

    private static function cle(mixed $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii((string) $valeur))) ?? '';
    }

    private function date(mixed $valeur): ?string
    {
        if (is_numeric($valeur)) {
            return ExcelDate::excelToDateTimeObject((float) $valeur)->format('Y-m-d');
        }

        return $valeur ? date('Y-m-d', strtotime((string) $valeur)) : null;
    }

    private function heure(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        if (is_numeric($valeur)) {
            return ExcelDate::excelToDateTimeObject((float) $valeur)->format('H:i');
        }
        if (preg_match('/\b(\d{1,2})[hH:](\d{2})\b/', (string) $valeur, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^\d{1,2}$/', trim((string) $valeur))) {
            return sprintf('%02d:00', (int) $valeur);
        }

        return date('H:i', strtotime((string) $valeur));
    }
}
