<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\AnneeScolaire;
use Illuminate\Http\Request;

/**
 * Résout l'année scolaire visée par une requête de reporting : celle
 * explicitement demandée (et vérifiée comme appartenant à l'école), ou à
 * défaut l'année active de l'école courante.
 */
trait ResolutionAnneeScolaire
{
    private function resolveAnnee(Request $request, int $schoolId): int
    {
        if ($request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'))->id;
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail()->id;
    }
}
