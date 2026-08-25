<?php

namespace App\Services\Push;

/**
 * Résout `FCM_CREDENTIALS` en chemin absolu.
 *
 * Un hébergement mutualisé ne donne pas toujours un moyen simple de connaître
 * le chemin absolu du déploiement (pas de SSH, panel sans terminal) : accepter
 * un simple nom de fichier, résolu contre `storage/app/private/`, évite d'en
 * avoir besoin — ce dossier existe déjà et est hors du dépôt git (cf.
 * `storage/app/private/.gitignore`). Un chemin déjà absolu (Unix `/...` ou
 * Windows `C:\...`) reste pris tel quel, pour ne rien casser d'un
 * déploiement qui le renseignait déjà en entier.
 */
class FcmCredentials
{
    public static function chemin(): ?string
    {
        $valeur = config('services.fcm.credentials');

        if (! $valeur) {
            return null;
        }

        if (str_starts_with($valeur, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $valeur) === 1) {
            return $valeur;
        }

        return storage_path('app/private/'.ltrim($valeur, '/\\'));
    }
}
