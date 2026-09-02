<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Services\OrangeSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exemple d'utilisation directe d'OrangeSmsService : à la différence de
 * App\Services\Sms\SmsService (cf. ScolariteController::confirmerParSms), on
 * appelle ici le service Orange sans passer par le driver générique, pour
 * pouvoir renvoyer le `message_id` au client — utile pour un appel manuel
 * déclenché depuis l'interface (bouton "renvoyer le SMS"), où l'agent veut
 * un retour immédiat sur le succès de l'envoi.
 *
 * Pour un déclenchement automatique (paiement encaissé, absence détectée…),
 * préférer `SmsService::envoyer()` avec `SMS_DRIVER=orange` : les appelants
 * existants (ScolariteController, BusService, AbsenceNonEnregistreeService…)
 * n'ont alors rien à changer.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly OrangeSmsService $orangeSms) {}

    public function envoyerConfirmationPaiement(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::findOrFail($eleveId);
        $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

        if (! $tuteur?->telephone) {
            return ApiResponse::error("Aucun tuteur joignable par SMS pour cet élève.", 422);
        }

        $montant = number_format((int) $request->integer('montant'), 0, ',', ' ');
        $message = "Paiement de {$montant} F reçu pour {$eleve->nom_complet}. Merci.";

        $resultat = $this->orangeSms->sendSms($tuteur->telephone, $message);

        if (! $resultat['success']) {
            return ApiResponse::error("Échec de l'envoi du SMS : {$resultat['error']}", 502);
        }

        return ApiResponse::success(['message_id' => $resultat['message_id']], 'SMS envoyé.');
    }
}
