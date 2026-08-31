<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BusVersement;
use App\Support\SignatureVersementBus;
use Illuminate\Http\JsonResponse;

/**
 * Vérification publique d'authenticité d'un reçu de transport scolaire, via
 * le QR code imprimé dessus — même principe que
 * {@see VerificationVersementController} pour la scolarité.
 */
class VerificationVersementBusController extends Controller
{
    public function show(int $versementId, string $signature): JsonResponse
    {
        if (! SignatureVersementBus::verifier($versementId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun reçu authentique.', 422);
        }

        $versement = BusVersement::with(['affectation.eleve.classe', 'affectation.trajet.school'])->find($versementId);

        if (! $versement) {
            return ApiResponse::notFound('Reçu introuvable.');
        }

        $affectation = $versement->affectation;

        return ApiResponse::success([
            'numero_recu' => $versement->numero_recu,
            'eleve' => [
                'nom_complet' => $affectation->eleve->nom_complet,
                'matricule' => $affectation->eleve->matricule,
            ],
            'classe' => $affectation->eleve->classe?->nom,
            'ecole' => $affectation->trajet->school?->name,
            'trajet' => $affectation->trajet->nom,
            'mois' => $versement->mois->format('Y-m-d'),
            'montant' => $versement->montant,
            'date_versement' => $versement->date_versement->format('Y-m-d'),
            'mode' => $versement->mode,
            'annule' => $versement->annule_le !== null,
        ]);
    }
}
