<?php

namespace App\Mail;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Code à usage unique pour la réinitialisation du mot de passe (cf.
 * {@see AuthService::demanderOtp()}). En file d'attente : rien
 * dans l'app n'envoie encore de courriel de façon synchrone, et une SMTP
 * lente ne doit pas retarder la réponse à l'utilisateur qui vient de
 * demander son code.
 */
class OtpMotDePasseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $otp,
        public readonly int $validiteMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.otp-mot-de-passe',
            with: [
                'nom' => $this->user->name,
                'otp' => $this->otp,
                'validiteMinutes' => $this->validiteMinutes,
            ],
        );
    }
}
