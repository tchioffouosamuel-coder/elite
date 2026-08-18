<?php

namespace App\Support;

/**
 * Signature d'authenticité d'un reçu de versement : un HMAC de l'identifiant
 * du versement, dérivé de la clé applicative, sans rien stocker en base —
 * même principe que `SignatureBulletin`, appliqué au reçu de paiement plutôt
 * qu'au bulletin.
 */
class SignatureVersement
{
    public static function signer(int $versementId): string
    {
        return substr(hash_hmac('sha256', (string) $versementId, config('app.key')), 0, 20);
    }

    public static function verifier(int $versementId, string $signature): bool
    {
        return hash_equals(self::signer($versementId), $signature);
    }

    /** Lien de vérification public, à encoder dans le QR code du reçu. */
    public static function lienVerification(int $versementId): string
    {
        $signature = self::signer($versementId);

        return rtrim(config('app.frontend_url'), '/')."/verification-versement/{$versementId}/{$signature}";
    }
}
