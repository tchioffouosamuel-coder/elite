<?php

namespace App\Support\Pdf;

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
    public static function make(array $options = []): Mpdf
    {
        // mPDF remplace entièrement `fontDir`/`fontdata` si on les passe en
        // option (pas de fusion récursive côté lib) : on repart donc de ses
        // propres valeurs par défaut pour ne pas perdre DejaVu et les polices
        // core en ajoutant Montserrat.
        $fontDir = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        return new Mpdf(array_merge([
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
    }

    public static function streamFromView(string $view, array $data, string $filename, array $options = []): Response
    {
        $html = view($view, $data)->render();
        $mpdf = self::make($options);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
