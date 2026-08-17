<?php

namespace App\Support\Pdf;

use App\Models\School;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

/**
 * _smapp génère ses documents HTML (bulletins, bilans disciplinaires) via mPDF plutôt que dompdf.
 * On reprend le même moteur pour rester fidèle à son rendu (pagination, polices, en-têtes HTML).
 */
class MpdfFactory
{
    /** Largeur du filigrane en millimètres — un peu moins de la moitié d'une A4. */
    private const FILIGRANE_LARGEUR = 90;

    /**
     * Opacité du filigrane. Assez marqué pour identifier l'établissement d'un
     * coup d'œil sur une photocopie, assez discret pour ne pas gêner la
     * lecture d'un tableau de notes par-dessus.
     */
    private const FILIGRANE_OPACITE = 0.07;

    public static function make(array $options = [], ?School $school = null): Mpdf
    {
        // mPDF remplace entièrement `fontDir`/`fontdata` si on les passe en
        // option (pas de fusion récursive côté lib) : on repart donc de ses
        // propres valeurs par défaut pour ne pas perdre DejaVu et les polices
        // core en ajoutant Montserrat.
        $fontDir = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf(array_merge([
            'tempDir' => storage_path('app/mpdf'),
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'montserrat',
            'fontDir' => array_merge($fontDir, [resource_path('fonts/montserrat')]),
            'fontdata' => array_merge($fontData, [
                'montserrat' => [
                    'R' => 'Montserrat-Regular.ttf',
                    'B' => 'Montserrat-Bold.ttf',
                    'I' => 'Montserrat-Italic.ttf',
                    'BI' => 'Montserrat-BoldItalic.ttf',
                ],
            ]),
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 8,
            'margin_bottom' => 10,
        ], $options));

        self::appliquerFiligrane($mpdf, $school);

        return $mpdf;
    }

    /**
     * Logo de l'établissement en filigrane, centré sur chaque page.
     *
     * mPDF le compose sous le contenu et le répète de lui-même : inutile de
     * l'insérer dans le flux HTML, où il aurait décalé la mise en page et ne
     * serait apparu que sur la première page.
     *
     * `$largeurMm`/`$opacite` permettent à un document précis (le bulletin,
     * par exemple) de s'écarter du filigrane par défaut sans changer le
     * réglage commun à tous les autres documents.
     */
    public static function appliquerFiligrane(Mpdf $mpdf, ?School $school, ?float $largeurMm = null, ?float $opacite = null): void
    {
        $logo = $school?->logo_path
            ? storage_path('app/public/'.ltrim($school->logo_path, '/'))
            : null;

        if ($logo === null || ! is_file($logo)) {
            return;
        }

        // Passé en nombre simple, le 3e argument de SetWatermarkImage n'est PAS
        // une largeur en mm mais une marge retranchée de chaque bord de page
        // (cf. Mpdf::Image(), branche `watermark`) : plus la valeur est grande,
        // plus l'image rétrécit — au-delà d'environ 100 sur une A4 (210mm), la
        // largeur calculée devient négative et le filigrane disparaît purement
        // et simplement. Une taille explicite ne peut donc s'obtenir qu'en
        // passant un tableau [largeur, hauteur] en mm, calculé ici à partir du
        // ratio réel de l'image pour ne pas la déformer.
        $taille = $largeurMm !== null
            ? self::tailleFiligrane($logo, $largeurMm)
            : self::FILIGRANE_LARGEUR;

        $mpdf->SetWatermarkImage($logo, $opacite ?? self::FILIGRANE_OPACITE, $taille, 'P');
        $mpdf->showWatermarkImage = true;
    }

    /** @return array{0: float, 1: float} */
    private static function tailleFiligrane(string $chemin, float $largeurMm): array
    {
        $dimensions = @getimagesize($chemin);

        if ($dimensions === false || $dimensions[0] <= 0) {
            return [$largeurMm, $largeurMm];
        }

        return [$largeurMm, $largeurMm * $dimensions[1] / $dimensions[0]];
    }

    public static function streamFromView(string $view, array $data, string $filename, array $options = [], ?School $school = null): Response
    {
        $html = view($view, $data)->render();
        $mpdf = self::make($options, $school);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
