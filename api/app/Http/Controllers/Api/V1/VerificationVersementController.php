<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Versement;
use App\Support\SignatureVersement;
use Illuminate\Http\JsonResponse;

/**
 * Vérification publique d'authenticité d'un reçu de versement, via le QR
 * code imprimé dessus — accessible sans authentification puisque c'est un
 * tiers externe qui scanne, pas un utilisateur de la plateforme. Ne renvoie
 * donc que des données déjà visibles sur le reçu lui-même : aucune
 * coordonnée de tuteur ni information sensible.
 */
class VerificationVersementController extends Controller
{
    public function show(int $versementId, string $signature): JsonResponse
    {
        if (! SignatureVersement::verifier($versementId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun reçu authentique.', 422);
        }

        $versement = Versement::with(['dossier.eleve.classe', 'dossier.school'])->find($versementId);

        if (! $versement) {
            return ApiResponse::notFound('Reçu introuvable.');
        }

        $dossier = $versement->dossier;

        return ApiResponse::success([
            'numero_recu' => $versement->numero_recu,
            'eleve' => [
                'nom_complet' => $dossier->eleve->nom_complet,
                'matricule' => $dossier->eleve->matricule,
            ],
            'classe' => $dossier->eleve->classe?->nom,
            'ecole' => $dossier->school->name,
            'montant' => $versement->montant,
            'date_versement' => $versement->date_versement->format('Y-m-d'),
            'mode' => $versement->mode,
            'annule' => $versement->annule_le !== null,
        ]);
    }
}
