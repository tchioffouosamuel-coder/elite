<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Support\SignatureEmploiDuTemps;
use Illuminate\Http\JsonResponse;

class VerificationEmploiDuTempsController extends Controller
{
    public function show(int $classeId, int $anneeId, string $signature): JsonResponse
    {
        $classe = Classe::with('school')->findOrFail($classeId);
        $annee = AnneeScolaire::where('school_id', $classe->school_id)->findOrFail($anneeId);

        if (! SignatureEmploiDuTemps::verifier($classe->id, $annee->id, $signature)) {
            return ApiResponse::error('Document invalide / Invalid document.', 403);
        }

        return ApiResponse::success([
            'valide' => true,
            'type' => 'emploi_du_temps',
            'classe' => $classe->nom,
            'ecole' => $classe->school?->name,
            'annee_scolaire' => $annee->libelle,
            'message' => 'Document authentique / Authentic document',
        ]);
    }
}
