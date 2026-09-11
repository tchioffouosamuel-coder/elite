<?php

namespace App\Support;

/** Signature HMAC d'un emploi du temps de classe et de son année active. */
class SignatureEmploiDuTemps
{
    public static function signer(int $classeId, int $anneeId): string
    {
        return substr(hash_hmac('sha256', "{$classeId}:{$anneeId}", config('app.key')), 0, 20);
    }

    public static function verifier(int $classeId, int $anneeId, string $signature): bool
    {
        return hash_equals(self::signer($classeId, $anneeId), $signature);
    }

    public static function lienVerification(int $classeId, int $anneeId): string
    {
        return rtrim(config('app.frontend_url'), '/') . "/verification-emploi-du-temps/{$classeId}/{$anneeId}/" . self::signer($classeId, $anneeId);
    }
}
