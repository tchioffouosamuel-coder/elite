<?php

namespace App\Support;

/**
 * Montant en toutes lettres, en anglais — la mention « Total amount: … »
 * attendue par les banques sur l'ordre de virement des salaires
 * (cf. BordereauVirementGenerator). Un franc CFA n'a pas de subdivision : ni
 * décimales, ni "cents" à écrire.
 */
class NombreEnLettresAnglais
{
    private const UNITES = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen',
    ];

    private const DIZAINES = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety',
    ];

    public static function convertir(int $montant): string
    {
        if ($montant === 0) {
            return 'zero';
        }

        if ($montant < 0) {
            return 'minus '.self::convertir(-$montant);
        }

        $millions = intdiv($montant, 1_000_000);
        $reste = $montant % 1_000_000;
        $milliers = intdiv($reste, 1_000);
        $centaines = $reste % 1_000;

        $mots = [];

        if ($millions > 0) {
            $mots[] = self::moinsDeMille($millions).' million'.($millions > 1 ? 's' : '');
        }

        if ($milliers > 0) {
            $mots[] = self::moinsDeMille($milliers).' thousand';
        }

        if ($centaines > 0) {
            $mots[] = self::moinsDeMille($centaines);
        }

        return implode(' ', $mots);
    }

    /** @param  int  $nombre  0 à 999 */
    private static function moinsDeMille(int $nombre): string
    {
        $centaine = intdiv($nombre, 100);
        $reste = $nombre % 100;

        $mots = [];

        if ($centaine > 0) {
            $mots[] = self::UNITES[$centaine].' hundred';
        }

        if ($reste > 0) {
            $mots[] = ($centaine > 0 ? 'and ' : '').self::deuxChiffres($reste);
        }

        return implode(' ', $mots);
    }

    /** @param  int  $nombre  1 à 99 */
    private static function deuxChiffres(int $nombre): string
    {
        if ($nombre < 20) {
            return self::UNITES[$nombre];
        }

        $dizaine = intdiv($nombre, 10);
        $unite = $nombre % 10;

        return self::DIZAINES[$dizaine].($unite > 0 ? '-'.self::UNITES[$unite] : '');
    }
}
