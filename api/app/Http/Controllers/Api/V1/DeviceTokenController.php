<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Enregistrement des appareils mobiles pour les notifications push.
 *
 * Le jeton FCM appartient à l'installation, pas au compte : sur un téléphone
 * partagé entre deux surveillants, il doit changer de propriétaire à chaque
 * connexion, faute de quoi le précédent continuerait de recevoir les
 * notifications du suivant.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'jeton' => ['required', 'string', 'max:255'],
            'plateforme' => ['required', Rule::in(DeviceToken::PLATEFORMES)],
        ]);

        // `updateOrCreate` sur le jeton seul : réenregistrer un appareil déjà
        // connu le réattribue à l'utilisateur courant au lieu d'échouer sur
        // la contrainte d'unicité.
        $appareil = DeviceToken::updateOrCreate(
            ['jeton' => $donnees['jeton']],
            [
                'user_id' => $request->user()->id,
                'plateforme' => $donnees['plateforme'],
                'school_id' => app('tenant.school_id'),
                'derniere_utilisation' => now(),
            ]
        );

        return ApiResponse::success(
            ['id' => $appareil->id],
            'Appareil enregistré pour les notifications.'
        );
    }

    /**
     * Retire l'appareil, à la déconnexion. Volontairement silencieux si le
     * jeton est déjà inconnu : une déconnexion ne doit jamais échouer pour
     * cette raison.
     */
    public function destroy(Request $request): JsonResponse
    {
        $donnees = $request->validate(['jeton' => ['required', 'string', 'max:255']]);

        DeviceToken::where('jeton', $donnees['jeton'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return ApiResponse::success(message: 'Appareil retiré.');
    }
}
