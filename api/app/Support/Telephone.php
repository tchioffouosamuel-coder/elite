<?php

namespace App\Support;

/**
 * Normalisation d'un numéro camerounais vers le format E.164
 * (« 692 34 21 06 » → « +237692342106 ») — seule forme comparable de façon
 * fiable, deux saisies du même numéro ne s'écrivant presque jamais pareil.
 *
 * Point de vérité unique : la connexion par téléphone
 * ({@see \App\Services\AuthService}) et l'ouverture d'un accès parent
 * ({@see \App\Services\CompteParentService}) doivent s'accorder sur exactement
 * la même forme, sans quoi un compte se retrouve introuvable au login.
 */
class Telephone
{
    public static function normaliser(string $telephone): string
    {
        $nettoye = preg_replace('/[^\d+]/', '', $telephone) ?? $telephone;

        if (str_starts_with($nettoye, '+')) {
            return $nettoye;
        }

        // L'indicatif est déjà présent mais sans le "+" (numéro copié depuis
        // une autre source, saisi manuellement…) : ne pas le rajouter, sous
        // peine d'un "+237237692342106" qui ne correspond à aucune ligne.
        if (str_starts_with($nettoye, '237') && strlen($nettoye) === 12) {
            return '+'.$nettoye;
        }

        // Les numéros locaux à 9 chiffres commencent par 6 (mobile) ou 2
        // (fixe) ; un 0 initial est un préfixe national à retirer.
        $nettoye = ltrim($nettoye, '0');

        return '+237'.$nettoye;
    }
}
