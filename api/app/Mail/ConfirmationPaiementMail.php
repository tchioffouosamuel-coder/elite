<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation d'un encaissement (scolarité ou bus) au tuteur — en file
 * d'attente comme {@see OtpMotDePasseMail} : un SMTP lent ne doit jamais
 * retarder la réponse au caissier qui vient d'enregistrer le paiement.
 */
class ConfirmationPaiementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $titre,
        public readonly string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titre);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.confirmation-paiement',
            with: ['titre' => $this->titre, 'message' => $this->message],
        );
    }
}
