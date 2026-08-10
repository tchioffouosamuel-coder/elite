<?php

namespace App\Support\Pdf;

use Mpdf\Mpdf;

/**
 * _smapp génère ses documents HTML (bulletins, bilans disciplinaires) via mPDF plutôt que dompdf.
 * On reprend le même moteur pour rester fidèle à son rendu (pagination, polices, en-têtes HTML).
 */
class MpdfFactory
{
    public static function make(array $options = []): Mpdf
    {
        return new Mpdf(array_merge([
            'tempDir' => storage_path('app/mpdf'),
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 8,
            'margin_bottom' => 10,
        ], $options));
    }

    public static function streamFromView(string $view, array $data, string $filename, array $options = []): \Symfony\Component\HttpFoundation\Response
    {
        $html = view($view, $data)->render();
        $mpdf = self::make($options);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
