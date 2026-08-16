<?php

namespace App\Support\Pdf;

use App\Models\Depense;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Bilan des dépenses d'une période : le détail chronologique, puis la
 * ventilation par poste comptable.
 *
 * Les deux vues figurent sur le même document parce qu'elles se contrôlent
 * l'une l'autre : le total de la ventilation doit retomber sur celui du
 * détail, et un écart saute aux yeux au moment de signer.
 */
class BilanDepensesGenerator
{
    use RenduDocument;

    /**
     * @param  Collection<int, Depense>  $depenses
     * @param  list<array{code: string, libelle: string, nombre: int, montant: int}>  $parCompte
     * @param  array{nombre: int, engage: int, paye: int, total: int, annule: int}  $totaux
     */
    public function build(School $school, Collection $depenses, array $parCompte, array $totaux, ?string $du, ?string $au): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle('Bilan des dépenses');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$this->enTeteEcole($school)
                .$this->titre($du, $au, $totaux)
                .$this->ventilation($parCompte, $totaux['total'])
                .$this->detail($depenses)
                .$this->signature($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.dep th{font-size:2.4mm}'
            .'.dep td{font-size:2.5mm;padding:1.2mm 1mm}'
            .'.dep .lib{text-align:left}'
            .'.dep .num{text-align:right}'
            .'.dep tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold}'
            .'.annulee td{color:#999;text-decoration:line-through}'
            .'.section{font-weight:bold;font-size:3mm;color:'.self::ARDOISE.';margin:4mm 0 1mm}';
    }

    /** @param array{nombre: int, engage: int, paye: int, total: int, annule: int} $totaux */
    private function titre(?string $du, ?string $au, array $totaux): string
    {
        $periode = $du || $au
            ? 'du '.($du ? $this->jour($du) : '…').' au '.($au ? $this->jour($au) : $this->jour(date('Y-m-d')))
            : 'depuis l\'ouverture';

        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Bilan des dépenses</span><br>'
            .'<span class="titre-en">Expenditure report</span>'
            .'</div>'
            .'<div style="background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:2.9mm;font-weight:bold;margin:3mm 0;">'
            .'Période <i>/ Period</i> : '.$this->e($periode)
            .' &nbsp;|&nbsp; '.$totaux['nombre'].' dépense(s)'
            .' &nbsp;|&nbsp; Payé : '.$this->francs($totaux['paye'])
            .' &nbsp;|&nbsp; Engagé : '.$this->francs($totaux['engage'])
            .'</div>';
    }

    /** @param list<array{code: string, libelle: string, nombre: int, montant: int}> $parCompte */
    private function ventilation(array $parCompte, int $total): string
    {
        if ($parCompte === []) {
            return '';
        }

        $html = '<div class="section">Ventilation par poste comptable</div>'
            .'<table class="dep"><thead><tr>'
            .'<th style="width:10%;">Compte</th>'
            .'<th class="lib" style="width:46%;">Libellé</th>'
            .'<th style="width:12%;">Nombre</th>'
            .'<th style="width:16%;">Montant</th>'
            .'<th style="width:16%;">Part</th>'
            .'</tr></thead><tbody>';

        foreach ($parCompte as $poste) {
            $part = $total > 0 ? round($poste['montant'] * 100 / $total, 1) : 0;

            $html .= '<tr>'
                .'<td><b>'.$this->e($poste['code']).'</b></td>'
                .'<td class="lib">'.$this->e($poste['libelle']).'</td>'
                .'<td>'.$poste['nombre'].'</td>'
                .'<td class="num">'.$this->francs($poste['montant']).'</td>'
                .'<td class="num">'.number_format($part, 1, ',', ' ').' %</td>'
                .'</tr>';
        }

        return $html.'<tr class="total">'
            .'<td colspan="3" class="lib">Total</td>'
            .'<td class="num">'.$this->francs($total).'</td>'
            .'<td class="num">100 %</td>'
            .'</tr></tbody></table>';
    }

    /** @param Collection<int, Depense> $depenses */
    private function detail(Collection $depenses): string
    {
        $html = '<div class="section">Détail chronologique</div>'
            .'<table class="dep"><thead><tr>'
            .'<th style="width:8%;">Date</th>'
            .'<th class="lib" style="width:26%;">Libellé</th>'
            .'<th class="lib" style="width:16%;">Bénéficiaire</th>'
            .'<th style="width:10%;">Compte</th>'
            .'<th style="width:10%;">Facture</th>'
            .'<th style="width:10%;">Mode</th>'
            .'<th style="width:10%;">État</th>'
            .'<th style="width:10%;">Montant</th>'
            .'</tr></thead><tbody>';

        if ($depenses->isEmpty()) {
            return $html.'<tr><td colspan="8" style="padding:6mm;">Aucune dépense sur la période.</td></tr></tbody></table>';
        }

        foreach ($depenses as $depense) {
            $annulee = $depense->statut === 'annulee';

            $html .= '<tr'.($annulee ? ' class="annulee"' : '').'>'
                .'<td>'.$depense->date_depense?->format('d/m/Y').'</td>'
                .'<td class="lib">'.$this->e($depense->libelle).'</td>'
                .'<td class="lib">'.$this->e($depense->beneficiaire ?: '—').'</td>'
                .'<td>'.$this->e($depense->compte?->code ?: '—').'</td>'
                .'<td>'.$this->e($depense->reference_facture ?: '—').'</td>'
                .'<td>'.$this->e($this->modeLibelle($depense->mode)).'</td>'
                .'<td>'.$this->e($this->statutLibelle($depense->statut)).'</td>'
                .'<td class="num">'.$this->francs($depense->montant).'</td>'
                .'</tr>';
        }

        // Le total du détail exclut les annulées, comme celui de la ventilation :
        // les deux doivent tomber sur le même montant.
        $retenu = (int) $depenses->where('statut', '!=', 'annulee')->sum('montant');

        return $html.'<tr class="total">'
            .'<td colspan="7" class="lib">Total hors dépenses annulées</td>'
            .'<td class="num">'.$this->francs($retenu).'</td>'
            .'</tr></tbody></table>';
    }

    private function signature(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;font-size:2.8mm;vertical-align:top;">'
            .'Arrêté le présent bilan aux montants portés en total.<br>'
            .'<i>Closed at the totals shown above.</i><br><br>'
            .'Fait à '.$this->e($ville !== '' ? $ville : '…………').', le '.date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:25%;text-align:center;font-size:2.8mm;">'
            .'<b>L\'Économe</b><br><i>The Bursar</i><br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature</span>'
            .'</td>'
            .'<td class="no-border" style="width:25%;text-align:center;font-size:2.8mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i><br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            .'</td></tr></table>';
    }

    private function modeLibelle(?string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile M.',
            'virement' => 'Virement',
            'cheque' => 'Chèque',
            'depot_bancaire' => 'Dépôt',
            default => 'Espèces',
        };
    }

    private function statutLibelle(string $statut): string
    {
        return match ($statut) {
            'engagee' => 'Engagée',
            'annulee' => 'Annulée',
            default => 'Payée',
        };
    }

    private function jour(string $date): string
    {
        return implode('/', array_reverse(explode('-', $date)));
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
