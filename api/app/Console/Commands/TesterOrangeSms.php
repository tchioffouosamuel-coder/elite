<?php

namespace App\Console\Commands;

use App\Services\OrangeSmsService;
use Illuminate\Console\Command;

/**
 * Commande de vérification manuelle en local : envoie un vrai SMS via Orange
 * pour valider les identifiants (.env) et le format de réponse réel, avant
 * de brancher le flux automatique (SMS_DRIVER=orange).
 *
 * Usage : php artisan sms:tester-orange 612345678 "Message de test"
 */
class TesterOrangeSms extends Command
{
    protected $signature = 'sms:tester-orange {telephone} {message=Ceci est un test.}';

    protected $description = 'Envoie un SMS de test via Orange (SMS Africa and Middle East) pour vérifier la configuration.';

    public function handle(OrangeSmsService $orangeSms): int
    {
        foreach (['client_id', 'client_secret', 'sender_address', 'sender_name'] as $cle) {
            if (! config("services.orange.{$cle}")) {
                $this->error("services.orange.{$cle} n'est pas configuré (voir ORANGE_* dans .env).");

                return self::FAILURE;
            }
        }

        $telephone = $this->argument('telephone');
        $message = $this->argument('message');

        $this->info("Récupération du token OAuth2…");

        try {
            $token = $orangeSms->getAccessToken();
            $this->info('Token obtenu : '.substr($token, 0, 12).'…');
        } catch (\Throwable $e) {
            $this->error('Échec de récupération du token : '.$e->getMessage());
            $this->line('Voir storage/logs/laravel.log pour le détail (statut HTTP, corps de la réponse).');

            return self::FAILURE;
        }

        $this->info("Envoi du SMS à {$telephone}…");

        $resultat = $orangeSms->sendSms($telephone, $message);

        $this->newLine();
        $this->table(['Champ', 'Valeur'], [
            ['success', $resultat['success'] ? 'oui' : 'non'],
            ['message_id', $resultat['message_id'] ?? '(aucun)'],
            ['error', $resultat['error'] ?? '(aucune)'],
        ]);

        if (! $resultat['success']) {
            $this->line('Voir storage/logs/laravel.log pour le détail complet de la réponse Orange.');

            return self::FAILURE;
        }

        $this->info('SMS envoyé. Ligne correspondante dans sms_logs :');
        $this->line(
            \App\Models\SmsLog::where('message_id', $resultat['message_id'])->latest()->first()?->toJson(JSON_PRETTY_PRINT)
                ?? '(introuvable — inattendu)'
        );

        return self::SUCCESS;
    }
}
