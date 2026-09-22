<?php

namespace App\Support\Pdf;

use App\Models\Personnel;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

class FichePresencePersonnelGenerator
{
    use RenduDocument;

    /** @param Collection<int, Personnel> $personnels */
    public function build(School $school, string $date, Collection $personnels): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
        ], $school);
        $mpdf->SetTitle('Fiche de présence journalière du personnel');

        $html = $this->enTeteEcole($school)
            .'<div style="text-align:center;line-height:1.4;margin-bottom:3mm;">'
            .'<span class="titre">Fiche de présence journalière du personnel</span><br>'
            .'<span class="titre-en">Daily staff attendance sheet</span><br>'
            .'<span class="mini">Date : '.date('d/m/Y', strtotime($date)).'</span>'
            .'</div>'
            .$this->tableau($personnels);

        $mpdf->WriteHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            .$this->stylesBase()
            .'.presence th{font-size:2.7mm}.presence td{height:8mm;font-size:2.8mm;padding:1mm}'
            .'.presence .num{width:8mm}.presence .nom{text-align:left;font-weight:bold;text-transform:uppercase}'
            .'.presence .heure{width:34mm}.note{font-size:2.3mm;margin-top:3mm;color:#555}'
            .'</style></head><body>'.$html.'</body></html>');

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** @param Collection<int, Personnel> $personnels */
    private function tableau(Collection $personnels): string
    {
        $html = '<table class="presence"><thead><tr>'
            .'<th class="num">N°</th><th>Nom</th><th class="heure">Heure d\'arrivée</th><th class="heure">Heure de départ</th>'
            .'</tr></thead><tbody>';

        $rang = 1;
        foreach ($personnels as $personnel) {
            $html .= '<tr><td>'.$rang++.'</td><td class="nom">'.$this->e($personnel->nom_complet).'</td><td></td><td></td></tr>';
        }

        if ($personnels->isEmpty()) {
            $html .= '<tr><td colspan="4" style="height:12mm;">Aucun personnel actif.</td></tr>';
        }

        return $html.'</tbody></table>'
            .'<div class="note">Document à déposer à l\'entrée. Chaque agent renseigne son heure d\'arrivée puis son heure de départ. '
            .'Après OCR, importer le fichier Excel avec les mêmes colonnes.</div>';
    }
}
