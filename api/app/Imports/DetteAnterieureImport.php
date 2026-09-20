<?php

namespace App\Imports;

use App\Models\Eleve;
use App\Services\ScolariteService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/** Import de dettes antérieures rapprochées par matricule. */
class DetteAnterieureImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
        'matricule' => 'matricule',
        'id' => 'matricule',
        'nom' => 'nom',
        'nomcomplet' => 'nom',
        'nom_complet' => 'nom',
        'dette' => 'dette',
        'montant' => 'dette',
    ];

    public int $importedCount = 0;

    /** @var array<int, string> */
    public array $erreurs = [];

    /** @param list<int> $schoolIds */
    public function __construct(
        private readonly array $schoolIds,
        private readonly ?int $schoolId = null,
        private readonly ?int $saisiPar = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $service = app(ScolariteService::class);

        foreach ($rows as $index => $row) {
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);
            $numero = $index + 2;

            if ($ligne['matricule'] === null && $ligne['nom'] === null && $ligne['dette'] === null) {
                continue;
            }

            if ($ligne['matricule'] === null || $ligne['dette'] === null || $ligne['dette'] <= 0) {
                $this->erreurs[] = "Ligne {$numero} : matricule et dette positive obligatoires.";
                continue;
            }

            $eleves = Eleve::forSchool($this->schoolIds)
                ->when($this->schoolId !== null, fn($query) => $query->where('school_id', $this->schoolId))
                ->where('matricule', $ligne['matricule'])
                ->get();

            if ($eleves->count() > 1 && $ligne['nom'] !== null) {
                $eleves = $eleves->filter(fn(Eleve $eleve) => self::cle($eleve->nom_complet) === self::cle($ligne['nom']))->values();
            }

            if ($eleves->count() !== 1) {
                $raison = $eleves->isEmpty() ? 'élève introuvable' : 'matricule non unique';
                $this->erreurs[] = "Ligne {$numero} ({$ligne['matricule']}) : {$raison}.";
                continue;
            }

            try {
                $service->enregistrerDetteAnterieure(
                    $eleves->first(),
                    $ligne['dette'],
                    'Import dettes antérieures',
                    $this->saisiPar,
                );
                $this->importedCount++;
            } catch (Throwable $e) {
                $this->erreurs[] = "Ligne {$numero} ({$ligne['matricule']}) : {$e->getMessage()}";
            }
        }
    }

    /** @return array{matricule: ?string, nom: ?string, dette: ?int} */
    private function normaliser(array $data): array
    {
        $ligne = [];
        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[self::cle((string) $entete)] ?? null;
            if ($cle !== null && ! array_key_exists($cle, $ligne)) {
                $ligne[$cle] = is_string($valeur) ? trim($valeur) : $valeur;
            }
        }

        return [
            'matricule' => isset($ligne['matricule']) && trim((string) $ligne['matricule']) !== '' ? trim((string) $ligne['matricule']) : null,
            'nom' => isset($ligne['nom']) && trim((string) $ligne['nom']) !== '' ? trim((string) $ligne['nom']) : null,
            'dette' => isset($ligne['dette']) && is_numeric(str_replace([' ', ' ', ','], ['', '', '.'], (string) $ligne['dette']))
                ? (int) round((float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $ligne['dette']))
                : null,
        ];
    }

    private static function cle(?string $valeur): string
    {
        return Str::of((string) $valeur)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }
}
