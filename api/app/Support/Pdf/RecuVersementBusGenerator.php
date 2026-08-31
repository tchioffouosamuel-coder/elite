<?php

namespace App\Support\Pdf;

use App\Models\BusVersement;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use App\Support\SignatureVersementBus;
use Endroid\QrCode\Builder\Builder;
use Mpdf\Output\Destination;

/**
 * Reçu du transport scolaire, au même format que le ticket de scolarité
 * (rouleau 80 mm) — mais pour un seul mois, puisque chaque mensualité de bus
 * porte son propre reçu plutôt que de se fondre dans l'historique annuel de
 * la scolarité.
 */
class RecuVersementBusGenerator
{
    use RenduDocument;

    private const FORMAT = [80, 160];

    public function build(BusVersement $versement): string
    {
        $versement->loadMissing(['affectation.eleve.classe', 'affectation.anneeScolaire', 'affectation.trajet.school', 'encaisseur']);

        $affectation = $versement->affectation;
        $school = $affectation->trajet->school;

        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        $mpdf->SetTitle('Reçu '.$versement->numero_recu);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->styles()
                .'</style></head><body>'
                .$this->enTete($school)
                .$this->titre()
                .$this->mentions($versement, $affectation)
                .$this->pied($versement)
                .$this->qrCode($versement)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function styles(): string
    {
        return 'body{font-family:montserrat,sans-serif;font-size:2.4mm;color:#000;margin:0}'
            .'.centre{text-align:center}'
            .'.ecole{font-weight:bold;font-size:2.7mm;line-height:1.2}'
            .'.titre{font-weight:bold;font-size:3mm;text-align:center;text-decoration:underline;margin:2mm 0}'
            .'table{width:100%;border-collapse:collapse}'
            .'td{padding:0.4mm 0;vertical-align:top;font-size:2.4mm}'
            .'.cle{font-weight:bold;width:46%}'
            .'.sep{border-top:0.4mm dashed #000;margin:2mm 0}'
            .'.montant{text-align:right;font-weight:bold}'
            .'.total{font-weight:bold;font-size:2.7mm}'
            .'.pied{font-size:2.1mm;margin-top:2.5mm}'
            .'.annule{color:#ac3527;font-weight:bold;text-align:center;font-size:3mm;margin:1.5mm 0}';
    }

    private function enTete(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);

        return '<div class="centre">'
            .($logo ? '<img src="'.$this->e($logo).'" style="width:16mm"><br>' : '')
            .'<span class="ecole">'.$this->e(mb_strtoupper($school->name)).'</span>'
            .'</div>';
    }

    private function titre(): string
    {
        return '<div class="titre">REÇU DE PAIEMENT / PAYMENT RECEIPT<br>DES FRAIS DE BUS / SCHOOL TRANSPORT FEES</div>';
    }

    private function mentions(BusVersement $versement, \App\Models\BusAffectation $affectation): string
    {
        $eleve = $affectation->eleve;

        $lignes = [
            ['Matricule / Student ID', $eleve->matricule ?: '—'],
            ['Nom / Name', mb_strtoupper($eleve->nom_complet)],
            ['Classe / Class', $eleve->classe?->nom ?? '—'],
            ['Trajet / Route', $affectation->trajet->nom],
            ['Année scolaire / School year', $affectation->anneeScolaire?->libelle ?? '—'],
            ['Mois couvert / Month covered', ucfirst($versement->mois->translatedFormat('F Y'))],
            ['Date / Date', $versement->date_versement->format('d/m/Y')],
            ['Mode / Payment method', $this->libelleMode($versement->mode)],
        ];

        $html = '<table>';
        foreach ($lignes as [$cle, $valeur]) {
            $html .= '<tr><td class="cle">'.$this->e($cle).'</td><td>'.$this->e((string) $valeur).'</td></tr>';
        }

        $html .= '</table><div class="sep"></div><table>'
            .'<tr><td class="cle total">Montant réglé / Amount paid</td><td class="montant total">'.$this->francs($versement->montant).'</td></tr>'
            .'</table>';

        if ($versement->estAnnule()) {
            $html .= '<div class="annule">*** REÇU ANNULÉ / RECEIPT CANCELLED ***</div>';
        }

        return $html;
    }

    private function qrCode(BusVersement $versement): string
    {
        try {
            $qr = (new Builder)->build(
                data: SignatureVersementBus::lienVerification($versement->id),
                size: 200,
                margin: 4,
            );

            return '<div class="sep"></div><div style="text-align:center">'
                .'<img src="'.$qr->getDataUri().'" style="width:20mm;height:20mm">'
                .'<div class="pied">Authenticité / Authenticity : scannez / scan to verify this receipt.</div>'
                .'</div>';
        } catch (\Throwable) {
            return '';
        }
    }

    private function pied(BusVersement $versement): string
    {
        return '<div class="sep"></div><div class="pied">'
            .'Reçu N° / Receipt No. <b>'.$this->e($versement->numero_recu).'</b><br>'
            .'Encaissé par / Collected by : '.$this->e($versement->encaisseur?->name ?? '—').'<br>'
            .'<i>Conservez ce reçu / Keep this receipt.</i>'
            .'</div>';
    }

    private function libelleMode(string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile Money / Mobile Money',
            'virement' => 'Virement / Bank transfer',
            'cheque' => 'Chèque / Cheque',
            'depot_bancaire' => 'Dépôt bancaire / Bank deposit',
            default => 'Espèces / Cash',
        };
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }
}
