<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /** @return bool succès de l'envoi. */
    public function envoyer(string $telephone, string $message): bool;
}
