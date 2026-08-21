<?php

namespace App\Support;

/**
 * Code-barres d'un article de l'inventaire, au format EAN-13.
 *
 * Pourquoi un vrai EAN-13 plutôt qu'un identifiant maison : c'est le symbole
 * que toute douchette lit sans configuration, et mPDF sait le tracer nativement
 * — aucune bibliothèque à ajouter, ni côté serveur ni côté navigateur.
 *
 * Le préfixe `200` appartient à la plage 200-299, que la norme réserve
 * justement à la numérotation interne d'un magasin : les codes émis ici ne
 * peuvent donc jamais entrer en collision avec ceux d'un produit du commerce
 * que l'établissement revendrait.
 *
 * Les neuf chiffres suivants portent l'identifiant de l'article, unique sur
 * toute la base : une étiquette se résout sans ambiguïté quel que soit le
 * comptoir qui la scanne, y compris dans un complexe à plusieurs écoles.
 */
class CodeBarreArticle
{
    /** Plage réservée à la numérotation interne (200-299). */
    private const PREFIXE = '200';

    /** 13 chiffres au total : 3 de préfixe, 9 de charge utile, 1 de contrôle. */
    private const LONGUEUR_CHARGE = 9;

    public static function pourArticle(int $articleId): string
    {
        $base = self::PREFIXE.str_pad((string) $articleId, self::LONGUEUR_CHARGE, '0', STR_PAD_LEFT);

        return $base.self::chiffreDeControle($base);
    }

    /** Identifiant d'article porté par un code émis ici, ou `null` si le code est étranger. */
    public static function articleId(string $code): ?int
    {
        if (! self::estValide($code) || ! str_starts_with($code, self::PREFIXE)) {
            return null;
        }

        return (int) substr($code, strlen(self::PREFIXE), self::LONGUEUR_CHARGE);
    }

    public static function estValide(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        return substr($code, 12, 1) === self::chiffreDeControle(substr($code, 0, 12));
    }

    /**
     * Clé de contrôle EAN-13 : somme pondérée 1-3-1-3… des douze premiers
     * chiffres, complétée à la dizaine supérieure.
     */
    private static function chiffreDeControle(string $douzeChiffres): string
    {
        $somme = 0;

        foreach (str_split($douzeChiffres) as $rang => $chiffre) {
            $somme += (int) $chiffre * ($rang % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $somme % 10) % 10);
    }
}
