<?php

namespace App\Services\Whatsapp;

interface WhatsappDriver
{
    /** @return bool succès de l'envoi. */
    public function envoyer(string $telephone, string $message): bool;
}
