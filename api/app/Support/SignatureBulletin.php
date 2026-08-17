<?php

namespace App\Support;

/**
 * Signature d'authenticité d'un bulletin : un HMAC de (élève, trimestre)
 * dérivé de la clé applicative, sans rien stocker en base — aucune table de
 * jetons à gérer, et impossible à forger sans connaître `APP_KEY`.
 *
 * Volontairement grossière : elle ne prouve pas qu'un bulletin PRÉCIS
 * (calculé à tel instant) est authentique, seulement que le lien pointe bien
 * vers cet élève et ce trimestre tels que la plateforme les connaît
 * aujourd'hui — largement suffisant pour dissuader un bulletin falsifié
 * présenté à un tiers (employeur, autre établissement).
 */
class SignatureBulletin
{
    public static function signer(int $eleveId, int $trimestreId): string
    {
        return substr(hash_hmac('sha256', "{$eleveId}:{$trimestreId}", config('app.key')), 0, 20);
    }

    public static function verifier(int $eleveId, int $trimestreId, string $signature): bool
    {
        return hash_equals(self::signer($eleveId, $trimestreId), $signature);
    }

    /** Lien de vérification public, à encoder dans le QR code du bulletin. */
    public static function lienVerification(int $eleveId, int $trimestreId): string
    {
        $signature = self::signer($eleveId, $trimestreId);

        return rtrim(config('app.frontend_url'), '/')."/verification-bulletin/{$eleveId}/{$trimestreId}/{$signature}";
    }
}
