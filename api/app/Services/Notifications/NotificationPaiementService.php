<?php

namespace App\Services\Notifications;

use App\Mail\ConfirmationPaiementMail;
use App\Models\Tuteur;
use App\Services\NotificationService;
use App\Services\Sms\SmsService;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Mail;

/**
 * Confirmation d'un encaissement (scolarité ou bus) au tuteur, sur les
 * canaux choisis au comptoir — utilisé par `ScolariteController` et
 * `BusPaiementController`, qui ne composent chacun que le message et le
 * titre, propres à leur registre.
 *
 * Chaque canal se tait plutôt que d'échouer quand la donnée manque (pas de
 * téléphone, pas d'email, pas de compte portail) : coché ou non, un canal
 * indisponible pour ce tuteur ne doit jamais faire échouer l'encaissement,
 * déjà enregistré.
 */
class NotificationPaiementService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly WhatsappService $whatsapp,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  list<string>  $canaux  sous-ensemble de sms|whatsapp|email|interne
     */
    public function notifier(?Tuteur $tuteur, array $canaux, int $schoolId, string $titre, string $message): void
    {
        if (! $tuteur || $canaux === []) {
            return;
        }

        if (in_array('sms', $canaux, true)) {
            $this->sms->envoyer($tuteur->telephone, $message);
        }

        if (in_array('whatsapp', $canaux, true)) {
            $this->whatsapp->envoyer($tuteur->telephone, $message);
        }

        if (in_array('email', $canaux, true) && $tuteur->email) {
            Mail::to($tuteur->email)->queue(new ConfirmationPaiementMail($titre, $message));
        }

        // Notification « simple » (cloche de l'application) : n'a de sens que
        // si ce tuteur porte un compte du portail parent — cf. Tuteur::user_id,
        // absent pour la plupart des tuteurs.
        if (in_array('interne', $canaux, true) && $tuteur->user_id) {
            $this->notifications->notifier($schoolId, [$tuteur->user_id], 'paiement', $titre, $message);
        }
    }
}
