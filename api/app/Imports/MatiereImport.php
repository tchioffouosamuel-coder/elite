<?php

namespace App\Imports;

use App\Models\Matiere;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) :
 * nom, nom_en, abbreviation, oral, ecrit, savoir_etre, pratique
 *
 * Primaire et maternelle uniquement : là où le secondaire rattache une
 * matière à un département, ici chaque matière porte son propre barème
 * réparti par volet. Le barème et la présence du volet pratique se déduisent
 * des points saisis par volet plutôt que d'être ressaisis en plus — ils
 * doivent de toute façon leur correspondre exactement (cf. StoreMatiereRequest).
 */
class MatiereImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public int $importedCount = 0;

    public function __construct(private readonly int $schoolId) {}

    public function model(array $row): ?Matiere
    {
        $oral = (float) ($row['oral'] ?? 0);
        $ecrit = (float) ($row['ecrit'] ?? 0);
        $savoirEtre = (float) ($row['savoir_etre'] ?? 0);
        $pratique = (float) ($row['pratique'] ?? 0);
        $evaluePratique = $pratique > 0;

        $repartition = ['oral' => $oral, 'ecrit' => $ecrit, 'savoir_etre' => $savoirEtre];
        if ($evaluePratique) {
            $repartition['pratique'] = $pratique;
        }

        $this->importedCount++;

        return new Matiere([
            'school_id' => $this->schoolId,
            'nom' => $row['nom'],
            'nom_en' => $row['nom_en'] ?? null,
            'abbreviation' => $row['abbreviation'] ?? null,
            'notation' => (int) round($oral + $ecrit + $savoirEtre + $pratique),
            'evalue_pratique' => $evaluePratique,
            'repartition_volets' => $repartition,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string'],
            'oral' => ['required', 'numeric', 'min:0'],
            'ecrit' => ['required', 'numeric', 'min:0'],
            'savoir_etre' => ['required', 'numeric', 'min:0'],
            'pratique' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
