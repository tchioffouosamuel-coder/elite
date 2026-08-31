<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Trimestre;
use Illuminate\Http\Request;

/**
 * Résout le trimestre visé par une requête de reporting : celui explicitement
 * demandé (et vérifié comme appartenant à l'école), ou à défaut le trimestre
 * actif de l'école courante — même principe que `ResolutionAnneeScolaire`.
 */
trait ResolutionTrimestre
{
    private function resolveTrimestre(Request $request, int $schoolId): int
    {
        $query = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId));

        if ($request->integer('trimestre_id')) {
            return $query->findOrFail($request->integer('trimestre_id'))->id;
        }

        return $query->where('is_active', true)->firstOrFail()->id;
    }
}
