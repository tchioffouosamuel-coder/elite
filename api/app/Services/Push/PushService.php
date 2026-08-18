<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Services\Push\Drivers\FcmPushDriver;
use App\Services\Push\Drivers\LogPushDriver;

/**
 * Point d'entrée unique des notifications push, sur le même modèle que
 * `App\Services\Sms\SmsService` : le driver réel dépend de
 * `services.push.driver`, et l'appelant n'a jamais à savoir lequel est actif.
 */
class PushService
{
    private PushDriver $driver;

    public function __construct()
    {
        $this->driver = match (config('services.push.driver', 'log')) {
            'fcm' => new FcmPushDriver,
            default => new LogPushDriver,
        };
    }

    /**
     * Notifie les appareils des utilisateurs listés.
     *
     * Comme pour le SMS, les échecs sont avalés : un push est un service
     * annexe et ne doit jamais faire échouer l'opération métier qui le
     * déclenche (enregistrement d'une absence, publication d'une annonce…).
     *
     * @param  iterable<int>  $userIds
     * @param  array<string, string|int>  $donnees
     */
    public function notifier(iterable $userIds, string $titre, string $message, array $donnees = []): int
    {
        $jetons = DeviceToken::whereIn('user_id', collect($userIds)->unique()->all())
            ->pluck('jeton')
            ->all();

        if ($jetons === []) {
            return 0;
        }

        try {
            return $this->driver->envoyer($jetons, $titre, $message, $donnees);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Message silencieux : réveille la synchronisation du téléphone sans rien
     * afficher. C'est lui qui donne l'impression de temps réel — l'app se met
     * à jour d'elle-même avant que l'utilisateur ne l'ouvre.
     *
     * @param  iterable<int>  $userIds
     */
    public function reveiller(iterable $userIds): int
    {
        return $this->notifier($userIds, '', '', ['type' => 'sync']);
    }
}
