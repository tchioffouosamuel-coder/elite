<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\Push\FcmCredentials;
use App\Services\Push\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Diagnostic de la configuration des notifications push, pour un compte qui
 * n'a pas la main sur le `.env` de production. `PushService` retombe
 * silencieusement sur `LogPushDriver` (les notifications s'écrivent dans les
 * logs au lieu de partir) dès que `PUSH_DRIVER` ou les identifiants Firebase
 * manquent — sans ça, rien ne le signale côté application.
 */
class PushDiagnosticController extends Controller
{
    /**
     * État de la config, sans jamais exposer le contenu des identifiants —
     * seulement leur présence/validité de forme.
     */
    public function index(): JsonResponse
    {
        $driver = config('services.push.driver', 'log');
        $projet = config('services.fcm.projet');
        // Chemin résolu, pas la valeur brute de `.env` : c'est l'endroit exact
        // où déposer le fichier, y compris quand `FCM_CREDENTIALS` n'est qu'un
        // nom de fichier relatif à `storage/app/private/` (cf. FcmCredentials).
        $cheminCredentials = FcmCredentials::chemin();

        $credentialsValides = false;
        $credentialsErreur = null;

        if (! $cheminCredentials) {
            $credentialsErreur = 'FCM_CREDENTIALS non défini.';
        } elseif (! is_file($cheminCredentials)) {
            $credentialsErreur = "Fichier introuvable sur le serveur : {$cheminCredentials}";
        } else {
            $compte = json_decode((string) file_get_contents($cheminCredentials), true);
            if (! is_array($compte) || ! isset($compte['client_email'], $compte['private_key'])) {
                $credentialsErreur = "Le fichier existe mais n'est pas un JSON de compte de service Firebase valide (client_email/private_key manquants).";
            } else {
                $credentialsValides = true;
            }
        }

        $actif = $driver === 'fcm' && (bool) $projet && $credentialsValides;

        return ApiResponse::success([
            'actif' => $actif,
            'driver' => $driver,
            'fcm_project_id' => $projet,
            // Chemin absolu résolu : là où déposer le fichier sur ce serveur,
            // même sans SSH pour le découvrir soi-même.
            'fcm_credentials_chemin' => $cheminCredentials,
            'fcm_credentials_valides' => $credentialsValides,
            'fcm_credentials_erreur' => $credentialsErreur,
            'appareils_enregistres_total' => DeviceToken::count(),
            'mes_appareils_enregistres' => DeviceToken::where('user_id', request()->user()->id)->count(),
        ], $actif
            ? 'Push Firebase actif.'
            : "Push non actif : les notifications sont générées mais s'arrêtent en log, sans partir vers les téléphones.");
    }

    /**
     * Envoie une vraie notification de test vers les appareils du compte
     * courant, pour vérifier le chemin complet (pas seulement la config).
     */
    public function test(Request $request): JsonResponse
    {
        $envoyes = app(PushService::class)->notifier(
            [$request->user()->id],
            'Test de notification',
            'Si vous recevez ceci, les notifications push fonctionnent.',
            ['type' => 'test'],
        );

        $mesAppareils = DeviceToken::where('user_id', $request->user()->id)->count();

        if ($mesAppareils === 0) {
            return ApiResponse::success(
                ['envoyes' => 0, 'appareils' => 0],
                "Aucun appareil enregistré pour votre compte : ouvrez l'app mobile connectée à ce compte, elle s'enregistre automatiquement."
            );
        }

        return ApiResponse::success(
            ['envoyes' => $envoyes, 'appareils' => $mesAppareils],
            $envoyes > 0
                ? "Notification transmise au driver pour {$envoyes} appareil(s) — vérifiez GET /diagnostics/push pour savoir si elle est réellement partie vers Firebase ou seulement journalisée."
                : 'Aucun envoi : le driver a échoué (voir storage/logs/laravel.log).'
        );
    }
}
