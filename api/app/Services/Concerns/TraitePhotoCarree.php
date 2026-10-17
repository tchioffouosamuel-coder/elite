<?php

namespace App\Services\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Recadre une photo en carré (centre) et la redimensionne en 600x600 JPEG —
 * extrait de `EleveService` (point 21 de la punch-list Elites-tech : le
 * personnel a besoin du même traitement pour sa propre photo de profil) afin
 * que les deux services partagent exactement la même tolérance aux formats
 * et aux fichiers corrompus, plutôt que deux copies qui divergeraient au
 * premier correctif apporté à l'une des deux.
 */
trait TraitePhotoCarree
{
    /** Décoder une image en tant que bitmap RVBA coûte largeur × hauteur × 4 octets à GD. */
    private const MAX_PIXELS_PHOTO = 40_000_000;

    /**
     * @param  array<string, mixed>  $contexteLog  Identifie le sujet dans les logs (ex. ['eleve_id' => 1]).
     * @return array{contenu: string, extension: string}
     */
    private function traiterPhotoCarree(UploadedFile $file, array $contexteLog): array
    {
        $chemin = $file->getRealPath();
        if ($chemin === false) {
            $this->logPhotoIllisible($file, '', $contexteLog);
            throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
        }

        $contenu = @file_get_contents($chemin);

        if ($contenu === false || $contenu === '') {
            $this->logPhotoIllisible($file, $chemin, $contexteLog);
            throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
        }

        // getimagesize() ne lit que l'en-tête (quelques Ko), sans décoder les
        // pixels : une photo de téléphone à très haute résolution ferait
        // dépasser le memory_limit de PHP dans imagecreatefromstring()
        // ci-dessous — une erreur fatale non rattrapable par try/catch. On la
        // rejette donc proprement avant, plutôt que de planter la requête.
        $dimensions = @getimagesizefromstring($contenu);
        if ($dimensions === false) {
            $this->logPhotoIllisible($file, $chemin, $contexteLog, $contenu);
            throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
        }
        [$width, $height, $type] = $dimensions;
        if ($width * $height > self::MAX_PIXELS_PHOTO) {
            throw new UnprocessableEntityHttpException('Photo trop grande (résolution excessive) : réduisez sa taille avant de l\'envoyer.');
        }

        // Certains hébergements PHP n'activent pas GD malgré la validation
        // correcte de l'image. Dans ce cas, conserver le fichier original
        // permet de terminer sans appeler une fonction absente.
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            $extension = $type === IMAGETYPE_PNG ? 'png' : 'jpg';

            return ['contenu' => $contenu, 'extension' => $extension];
        }

        // Marge de sécurité pour les photos de résolution normale mais
        // néanmoins volumineuses à décoder ; restaurée après coup.
        $limiteAnterieure = ini_set('memory_limit', '512M');

        try {
            $source = $this->decoderPhotoCarree($chemin, $contenu, $type);
            if ($source === false) {
                $this->logPhotoIllisible($file, $chemin, $contexteLog, $contenu);
                throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
            }

            $side = min($width, $height);
            $srcX = intdiv($width - $side, 2);
            $srcY = intdiv($height - $side, 2);

            $square = imagecreatetruecolor(600, 600);
            $blanc = imagecolorallocate($square, 255, 255, 255);
            imagefill($square, 0, 0, $blanc);
            imagecopyresampled($square, $source, 0, 0, $srcX, $srcY, 600, 600, $side, $side);
            imagedestroy($source);

            ob_start();
            imagejpeg($square, null, 90);
            $contents = ob_get_clean();
            imagedestroy($square);
        } finally {
            // @ : ini_set() émet un warning (converti en exception par Laravel)
            // s'il ne peut pas redescendre sous la mémoire déjà consommée par
            // le décodage ci-dessus. Sans conséquence : la limite ne compte que
            // pour la requête en cours, qui se termine juste après.
            if ($limiteAnterieure !== false) {
                @ini_set('memory_limit', $limiteAnterieure);
            }
        }

        return ['contenu' => $contents, 'extension' => 'jpg'];
    }

    /** @return \GdImage|false */
    private function decoderPhotoCarree(string $chemin, string $contenu, int $type): \GdImage|false
    {
        $decodeur = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? 'imagecreatefromjpeg' : null,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? 'imagecreatefrompng' : null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? 'imagecreatefromavif' : null,
            default => null,
        };

        if ($decodeur !== null) {
            $source = @$decodeur($chemin);

            if ($source !== false) {
                return $source;
            }
        }

        return @imagecreatefromstring($contenu);
    }

    /**
     * Trace le contexte d'un échec « image illisible » — sans ça, l'erreur
     * renvoyée au client ne dit pas si le fichier reçu est vide, tronqué, ou
     * dans un format que GD ne décode pas (ex. WebP/HEIC, non compilés dans
     * l'extension gd de l'image Docker).
     *
     * @param  array<string, mixed>  $contexteLog
     */
    private function logPhotoIllisible(UploadedFile $file, string $chemin, array $contexteLog, ?string $contenu = null): void
    {
        $tailleDisque = @filesize($chemin);
        $signature = $contenu !== null
            ? substr($contenu, 0, 12)
            : @file_get_contents($chemin, false, null, 0, 12);
        $gd = extension_loaded('gd') ? gd_info() : [];

        Log::warning('Photo illisible reçue', [
            ...$contexteLog,
            'nom_original' => $file->getClientOriginalName(),
            'mime_annonce' => $file->getClientMimeType(),
            'taille_annoncee' => $file->getSize(),
            'taille_sur_disque' => $tailleDisque,
            'signature_hex' => $signature !== false ? bin2hex($signature) : null,
            'gd_charge' => extension_loaded('gd'),
            'gd_jpeg' => $gd['JPEG Support'] ?? null,
            'gd_png' => $gd['PNG Support'] ?? null,
            'gd_webp' => $gd['WebP Support'] ?? null,
            'gd_avif' => $gd['AVIF Support'] ?? null,
        ]);
    }
}
