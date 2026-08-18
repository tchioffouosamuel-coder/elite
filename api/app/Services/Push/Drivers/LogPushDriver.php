<?php

namespace App\Services\Push\Drivers;

use App\Services\Push\PushDriver;
use Illuminate\Support\Facades\Log;

/**
 * Driver par défaut tant qu'aucun projet Firebase n'est configuré : la
 * notification s'écrit dans les logs au lieu de partir. Permet de vérifier que
 * les bons événements déclenchent bien un push, et vers combien d'appareils,
 * sans dépendre d'identifiants Firebase.
 */
class LogPushDriver implements PushDriver
{
    public function envoyer(array $jetons, string $titre, string $message, array $donnees = []): int
    {
        Log::info('[Push simulé] '.$titre.' — '.$message, [
            'appareils' => count($jetons),
            'donnees' => $donnees,
        ]);

        return count($jetons);
    }
}
