<?php

namespace App\Support\Audit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Rend journalisable une donnée reçue ou écrite : masque les secrets,
 * remplace les fichiers par leur description, tronque les textes longs
 * (une image en base64 rejouée par la synchronisation, par exemple).
 */
class Assainisseur
{
    public static function estSensible(string $champ): bool
    {
        $champ = Str::lower($champ);
        $config = config('audit.champs_sensibles');

        if (in_array($champ, $config['exacts'] ?? [], true)) {
            return true;
        }

        foreach ($config['contient'] ?? [] as $motif) {
            if (str_contains($champ, $motif)) {
                return true;
            }
        }

        return false;
    }

    public static function donnees(mixed $donnees): mixed
    {
        if ($donnees instanceof UploadedFile) {
            return [
                'fichier' => $donnees->getClientOriginalName(),
                'taille' => $donnees->getSize(),
                'mime' => $donnees->getClientMimeType(),
            ];
        }

        if (! is_array($donnees)) {
            return self::scalaire($donnees);
        }

        // Fichier encodé par l'outbox locale (cf. EnregistrerDansOutboxLocale::encoderFichier()).
        if (($donnees['__sync_fichier__'] ?? false) === true) {
            return ['fichier' => $donnees['nom'] ?? null, 'mime' => $donnees['mime'] ?? null];
        }

        $resultat = [];

        foreach ($donnees as $cle => $valeur) {
            $resultat[$cle] = is_string($cle) && self::estSensible($cle)
                ? '***'
                : self::donnees($valeur);
        }

        return $resultat;
    }

    public static function scalaire(mixed $valeur): mixed
    {
        if (is_string($valeur)) {
            $max = (int) config('audit.max_longueur_texte', 500);

            return mb_strlen($valeur) > $max
                ? mb_substr($valeur, 0, $max).'… ['.mb_strlen($valeur).' caractères]'
                : $valeur;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('Y-m-d H:i:s');
        }

        if (is_object($valeur)) {
            return method_exists($valeur, '__toString') ? (string) $valeur : $valeur::class;
        }

        return $valeur;
    }
}
