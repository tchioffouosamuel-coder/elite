<?php

namespace App\Services;

use App\Models\School;

/**
 * Compose cachet et signature scannés de l'établissement en une seule image
 * (la signature traverse le cachet, comme sur un tampon physique) plutôt que
 * de les juxtaposer avec du CSS ou des styles PhpWord : chaque moteur de
 * rendu (mPDF, dompdf, FPDF, Word) positionnerait ces deux calques
 * différemment, alors qu'une seule image composée s'affiche identiquement
 * partout — un simple <img>/addImage() par document, sans mise en page
 * spécifique à dupliquer dans chaque générateur.
 */
class VisaComposeService
{
    /** Marge ajoutée autour du cachet pour laisser la signature déborder, en proportion de sa taille. */
    private const PADDING_HAUT = 0.28;

    private const PADDING_DROITE = 0.32;

    private const PADDING_BAS = 0.06;

    private const PADDING_GAUCHE = 0.06;

    /** Largeur de la signature, en proportion de la largeur du cachet. */
    private const LARGEUR_SIGNATURE = 0.85;

    /**
     * Chemin disque de l'image à utiliser sur les documents : composée si le
     * cachet et la signature sont tous deux présents, sinon celle qui existe,
     * sinon `null`.
     */
    public function chemin(School $school): ?string
    {
        $cachet = $this->cheminSource($school->stamp_path);
        $signature = $this->cheminSource($school->signature_path);

        if ($cachet === null && $signature === null) {
            return null;
        }
        if ($cachet === null) {
            return $signature;
        }
        if ($signature === null) {
            return $cachet;
        }

        $dossier = storage_path('app/public/ecoles/'.$school->id);
        $composite = $dossier.'/visa-compose.png';

        // Régénéré seulement si l'une des deux sources a changé depuis le
        // dernier montage — recomposer à chaque document alourdirait chaque
        // génération pour un résultat identique la plupart du temps.
        if (is_file($composite) && filemtime($composite) >= max(filemtime($cachet), filemtime($signature))) {
            return $composite;
        }

        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $reussi = $this->composer($cachet, $signature, $composite);

        return $reussi ? $composite : $cachet;
    }

    private function cheminSource(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $complet = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($complet) ? $complet : null;
    }

    private function composer(string $cheminCachet, string $cheminSignature, string $sortie): bool
    {
        $cachet = $this->charger($cheminCachet);
        $signature = $this->charger($cheminSignature);

        if ($cachet === null || $signature === null) {
            return false;
        }

        $largeurCachet = imagesx($cachet);
        $hauteurCachet = imagesy($cachet);

        $largeurCanvas = (int) round($largeurCachet * (1 + self::PADDING_GAUCHE + self::PADDING_DROITE));
        $hauteurCanvas = (int) round($hauteurCachet * (1 + self::PADDING_HAUT + self::PADDING_BAS));

        $canvas = imagecreatetruecolor($largeurCanvas, $hauteurCanvas);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $xCachet = (int) round($largeurCachet * self::PADDING_GAUCHE);
        $yCachet = (int) round($hauteurCachet * self::PADDING_HAUT);
        imagecopy($canvas, $cachet, $xCachet, $yCachet, 0, 0, $largeurCachet, $hauteurCachet);

        $largeurSignature = (int) round($largeurCachet * self::LARGEUR_SIGNATURE);
        $hauteurSignature = (int) round(imagesy($signature) * ($largeurSignature / imagesx($signature)));

        $signatureRedim = imagecreatetruecolor($largeurSignature, $hauteurSignature);
        imagesavealpha($signatureRedim, true);
        imagealphablending($signatureRedim, false);
        imagefill($signatureRedim, 0, 0, imagecolorallocatealpha($signatureRedim, 0, 0, 0, 127));
        imagecopyresampled(
            $signatureRedim, $signature,
            0, 0, 0, 0,
            $largeurSignature, $hauteurSignature, imagesx($signature), imagesy($signature)
        );

        // Centrée sur le cachet puis décalée vers le haut-droite, pour le
        // traverser en diagonale plutôt que de simplement le recouvrir.
        $xSignature = $xCachet + (int) round($largeurCachet * 0.55) - (int) round($largeurSignature / 2);
        $ySignature = $yCachet + (int) round($hauteurCachet * 0.12);

        imagealphablending($canvas, true);
        imagecopy($canvas, $signatureRedim, $xSignature, $ySignature, 0, 0, $largeurSignature, $hauteurSignature);

        $reussi = imagepng($canvas, $sortie);

        imagedestroy($cachet);
        imagedestroy($signature);
        imagedestroy($signatureRedim);
        imagedestroy($canvas);

        return $reussi;
    }

    /** @return \GdImage|null */
    private function charger(string $chemin)
    {
        $type = @exif_imagetype($chemin);

        $image = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($chemin),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_GIF => @imagecreatefromgif($chemin),
            default => null,
        };

        return $image !== false ? $image : null;
    }
}
