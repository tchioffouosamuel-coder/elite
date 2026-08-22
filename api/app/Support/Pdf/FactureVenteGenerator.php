<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Models\VenteFourniture;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Facture du point de vente, au format ticket 80 mm — le même rouleau que le
 * reçu de scolarité, pour que le comptoir n'ait qu'une imprimante à alimenter.
 *
 * Le document reprend les libellés et prix figés sur les lignes de la vente,
 * jamais ceux de l'inventaire : réimprimer une facture doit rendre ce qui a
 * été remis à la famille, pas le tarif du jour.
 */
class FactureVenteGenerator
{
    use RenduDocument;

    /** Largeur du rouleau, hauteur généreuse : mPDF coupe au contenu. */
    private const FORMAT = [80, 200];

    public function build(VenteFourniture $vente): string
    {
        $vente->loadMissing(['lignes', 'eleve.classe', 'vendeur', 'school']);
        $school = $vente->school;

        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        $mpdf->SetTitle('Facture ' . $vente->numero_facture);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                . $this->styles()
                . '</style></head><body>'
                . $this->enTete($school)
                . '<div class="titre">FACTURE / INVOICE<br>FOURNITURES SCOLAIRES / SCHOOL SUPPLIES</div>'
                . ($vente->estAnnulee() ? '<div class="annule">— VENTE ANNULÉE / SALE CANCELLED —</div>' : '')
                . $this->mentions($vente)
                . $this->lignes($vente)
                . $this->pied($vente)
                . '</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function styles(): string
    {
        return 'body{font-family:montserrat,sans-serif;font-size:2.4mm;color:#000;margin:0}'
            . '.centre{text-align:center}'
            . '.ecole{font-weight:bold;font-size:2.7mm;line-height:1.2}'
            . '.titre{font-weight:bold;font-size:3mm;text-align:center;text-decoration:underline;margin:2mm 0}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'td{padding:0.4mm 0;vertical-align:top;font-size:2.4mm}'
            . '.cle{font-weight:bold;width:42%}'
            . '.sep{border-top:0.4mm dashed #000;margin:2mm 0}'
            . '.lignes td{border-bottom:0.2mm dotted #999;font-size:2.3mm;padding:0.7mm 0}'
            . '.qte{width:14%}'
            . '.pu{width:24%;text-align:right}'
            . '.montant{text-align:right;font-weight:bold;width:26%}'
            . '.total{font-weight:bold;font-size:3mm}'
            . '.pied{font-size:2.1mm;margin-top:2.5mm}'
            . '.annule{color:#ac3527;font-weight:bold;text-align:center;font-size:3mm;margin:1.5mm 0}';
    }

    private function enTete(?School $school): string
    {
        if ($school === null) {
            return '';
        }

        $logo = $this->cheminImage($school->logo_path);

        return '<div class="centre">'
            . ($logo ? '<img src="' . $this->e($logo) . '" style="width:16mm"><br>' : '')
            . '<span class="ecole">' . $this->e(mb_strtoupper($school->name)) . '</span>'
            . '</div>';
    }

    private function mentions(VenteFourniture $vente): string
    {
        $acheteur = $vente->eleve
            ? mb_strtoupper($vente->eleve->nom_complet) . ($vente->eleve->classe ? ' (' . $vente->eleve->classe->nom . ')' : '')
            : ($vente->client ?: 'Client au comptoir');

        $lignes = [
            ['Facture N° / Invoice No.', $vente->numero_facture],
            ['Date / Date', $vente->date_vente?->format('d/m/Y') ?? '—'],
            ['Acheteur / Customer', $acheteur],
            ['Paiement / Payment', $this->libelleMode($vente->mode)],
        ];

        $html = '<div class="sep"></div><table>';

        foreach ($lignes as [$cle, $valeur]) {
            $html .= '<tr><td class="cle">' . $this->e($cle) . '</td><td>' . $this->e($valeur) . '</td></tr>';
        }

        return $html . '</table>';
    }

    private function lignes(VenteFourniture $vente): string
    {
        $html = '<div class="sep"></div><table class="lignes">';
        $html .= '<tr><td><b>Article / Item</b></td><td class="qte"><b>Qté / Qty</b></td>'
            . '<td class="pu"><b>P.U. / Unit price</b></td><td class="montant"><b>Total / Total</b></td></tr>';

        foreach ($vente->lignes as $ligne) {
            $html .= '<tr>'
                . '<td>' . $this->e($ligne->libelle) . '</td>'
                . '<td class="qte">' . $ligne->quantite . '</td>'
                . '<td class="pu">' . $this->francs($ligne->prix_unitaire) . '</td>'
                . '<td class="montant">' . $this->francs($ligne->total) . '</td>'
                . '</tr>';
        }

        $html .= '</table><div class="sep"></div>';

        return $html . '<table><tr>'
            . '<td class="total">TOTAL / TOTAL</td>'
            . '<td class="total" style="text-align:right">' . $this->francs($vente->montant) . '</td>'
            . '</tr></table>';
    }

    private function pied(VenteFourniture $vente): string
    {
        return '<div class="sep"></div><div class="pied">'
            . 'Vendu par / Sold by : ' . $this->e($vente->vendeur?->name ?? '—') . '<br>'
            . ($vente->note ? $this->e($vente->note) . '<br>' : '')
            . '<i>Article vendu non repris / Sold item cannot be returned or exchanged.</i>'
            . '</div>';
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
        return number_format($montant, 0, ',', ' ') . ' F';
    }
}
