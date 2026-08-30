<?php

namespace App\Imports;

use App\Models\BusTrajet;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) : nom, description,
 * tarif_aller_simple, tarif_retour_simple, tarif_aller_retour.
 *
 * Le véhicule s'affecte après coup, trajet par trajet : un fichier d'import
 * n'a pas à connaître l'immatriculation de la flotte.
 */
class BusTrajetImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public int $importedCount = 0;

    public function __construct(private readonly int $schoolId)
    {
    }

    public function model(array $row): ?BusTrajet
    {
        $this->importedCount++;

        return new BusTrajet([
            'school_id' => $this->schoolId,
            'nom' => $row['nom'],
            'description' => $row['description'] ?? null,
            'tarif_aller_simple' => self::montant($row['tarif_aller_simple'] ?? null),
            'tarif_retour_simple' => self::montant($row['tarif_retour_simple'] ?? null),
            'tarif_aller_retour' => self::montant($row['tarif_aller_retour'] ?? null),
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string'],
        ];
    }

    /** Le fichier écrit parfois ses tarifs en texte (« 15 000 »). */
    private static function montant(mixed $valeur): ?int
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $nombre = preg_replace('/[^\d-]/', '', str_replace(',', '.', (string) $valeur));

        return $nombre === '' || $nombre === '-' ? null : (int) round((float) $nombre);
    }
}
