<?php

namespace App\Support\ImportExport;

use App\Exports\ExportGenerique;
use App\Exports\ModeleGenerique;
use App\Imports\ImportGenerique;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Les trois actions standard (import, export, modèle) pour une
 * {@see SpecificationModele} donnée. Consommé par le trait
 * {@see \App\Http\Controllers\Concerns\GereImportExport} (un modèle par
 * contrôleur) et directement par les contrôleurs qui exposent plusieurs
 * modèles (ex. `InfrastructureController` : infrastructures + équipements).
 */
class ActionsImportExport
{
    /** @return array{imported: int, updated: int, failed: int, errors: mixed} */
    public static function importer(SpecificationModele $spec, Request $request): array
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $import = new ImportGenerique($spec, $schoolId);
        Excel::import($import, $request->file('file'));

        return [
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];
    }

    public static function exporter(SpecificationModele $spec, string $nomFichier): BinaryFileResponse
    {
        return Excel::download(new ExportGenerique($spec, Tenant::schoolIds()), $nomFichier.'.xlsx');
    }

    public static function modele(SpecificationModele $spec, string $nomFichier): BinaryFileResponse
    {
        return Excel::download(
            new ModeleGenerique(array_values($spec->libellesTemplate())),
            'modele-'.$nomFichier.'.xlsx',
        );
    }
}
