<?php

namespace App\Support;

/**
 * Signature d'authenticité d'un reçu de transport scolaire — même principe
 * que {@see SignatureVersement} pour la scolarité, mais avec son propre lien
 * de vérification : un versement bus et un versement scolarité peuvent
 * partager le même identifiant numérique dans leurs tables respectives, la
 * route de vérification doit donc les distinguer.
 */
class SignatureVersementBus
{
    public static function signer(int $versementId): string
    {
        return substr(hash_hmac('sha256', 'bus:'.$versementId, config('app.key')), 0, 20);
    }

    public static function verifier(int $versementId, string $signature): bool
    {
        return hash_equals(self::signer($versementId), $signature);
    }

    /** Lien de vérification public, à encoder dans le QR code du reçu. */
    public static function lienVerification(int $versementId): string
    {
        $signature = self::signer($versementId);

        return rtrim(config('app.frontend_url'), '/')."/verification-versement-bus/{$versementId}/{$signature}";
    }
}
