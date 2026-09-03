<?php

namespace App\Http\Controllers\Concerns;

use App\Helpers\ApiResponse;
use App\Support\ImportExport\ActionsImportExport;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Active `import`, `export` et `modele` sur un contrôleur, pilotés par une
 * seule {@see SpecificationModele} — le triplet de routes standard
 * (`POST {model}/import`, `GET {model}/export`, `GET {model}/modele`)
 * appliqué à tout modèle « maître ». Un contrôleur qui expose plusieurs
 * modèles (ex. `InfrastructureController`) appelle
 * {@see ActionsImportExport} directement plutôt que ce trait. Un contrôleur
 * à règles métier réelles (Élèves, Personnel…) garde ses propres méthodes
 * dédiées.
 */
trait GereImportExport
{
    abstract protected function specificationImportExport(): SpecificationModele;

    /** Nom de fichier dérivé du nom de la Spec (ex. `NiveauSpec` -> `niveaux`). */
    protected function nomFichierImportExport(): string
    {
        $court = (new \ReflectionClass($this->specificationImportExport()))->getShortName();

        return strtolower(preg_replace('/Spec$/', '', $court));
    }

    public function import(Request $request): JsonResponse
    {
        $resultat = ActionsImportExport::importer($this->specificationImportExport(), $request);

        return ApiResponse::success(
            $resultat,
            "{$resultat['imported']} ligne(s) créée(s), {$resultat['updated']} mise(s) à jour.",
        );
    }

    public function export(): BinaryFileResponse
    {
        return ActionsImportExport::exporter($this->specificationImportExport(), $this->nomFichierImportExport());
    }

    public function modele(): BinaryFileResponse
    {
        return ActionsImportExport::modele($this->specificationImportExport(), $this->nomFichierImportExport());
    }
}
