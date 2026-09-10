<?php

namespace App\Support\Pdf;

use App\Models\BusVersement;
use App\Models\Versement;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

class RecuPreinscriptionGenerator
{
    use RenduDocument;

    public function build(?Versement $scolarite, ?BusVersement $bus): string
    {
        $school = $scolarite?->dossier?->school ?? $bus?->affectation?->trajet?->school;
        $eleve = $scolarite?->dossier?->eleve ?? $bus?->affectation?->eleve;
        $total = (int) ($scolarite?->montant ?? 0) + (int) ($bus?->montant ?? 0);
        $mpdf = MpdfFactory::make(['format' => [80, 170], 'margin_left' => 4, 'margin_right' => 4, 'margin_top' => 4, 'margin_bottom' => 4], $school);
        $mpdf->SetTitle('Reçu préinscription');
        $html = '<style>body{font-family:montserrat,sans-serif;font-size:2.6mm}h1{text-align:center;font-size:3.2mm;text-decoration:underline}table{width:100%;border-collapse:collapse}td{padding:1mm 0;border-bottom:.2mm dotted #999}.right{text-align:right;font-weight:bold}.total{font-weight:bold;font-size:3mm}</style>';
        $html .= '<h1>REÇU DE PRÉINSCRIPTION<br>SCOLARITÉ + BUS</h1>';
        $html .= '<p><b>Élève :</b> ' . $this->e($eleve?->nom_complet ?? '—') . '<br><b>Date :</b> ' . now()->format('d/m/Y') . '</p>';
        $html .= '<table>';
        if ($scolarite) {
            $html .= '<tr><td>Frais de scolarité</td><td class="right">' . $this->francs($scolarite->montant) . '</td></tr>';
        }
        if ($bus) {
            $html .= '<tr><td>Frais de bus (' . $this->e($bus->mois->translatedFormat('F Y')) . ')</td><td class="right">' . $this->francs($bus->montant) . '</td></tr>';
        }
        $html .= '<tr><td class="total">Total payé</td><td class="right total">' . $this->francs($total) . '</td></tr></table>';
        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
