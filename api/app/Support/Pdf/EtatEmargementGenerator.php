<?php

namespace App\Support\Pdf;

use App\Models\BulletinPaie;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * État d'émargement de la paie : la pièce que signent les agents pour attester
 * avoir perçu leur salaire, et que l'établissement conserve.
 *
 * Un bulletin remis prouve ce qui était dû ; seul l'émargement prouve que la
 * somme a été remise. C'est ce document qu'on ressort en cas de contestation,
 * d'où la colonne de signature laissée vide à l'impression et le rappel du
 * mode de règlement ligne par ligne.
 */
class EtatEmargementGenerator
{
    use RenduDocument;

    /**
     * @param  Collection<int, School>  $schools  Un établissement par page — le
     *                                             super admin en mode agrégé
     *                                             (aucun X-School-Id) voit tout
     *                                             le complexe, pas seulement
     *                                             l'école par défaut de son
     *                                             compte.
     * @param  Collection<int, BulletinPaie>  $bulletins  Bulletins de tous les établissements listés, mélangés.
     */
    public function build(Collection $schools, Collection $bulletins, string $periode): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $schools->first());
        $mpdf->SetTitle('État d\'émargement — '.$periode);

        $pages = $schools->map(function (School $school) use ($bulletins, $periode) {
            $bulletinsEcole = $bulletins->where('school_id', $school->id)->values();

            return $this->enTeteEcole($school)
                .$this->titre($periode, $bulletinsEcole)
                .$this->tableau($bulletinsEcole)
                .$this->pied($school);
        });

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$pages->implode('<pagebreak />')
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.etat th{font-size:2.4mm}'
            .'.etat td{font-size:2.6mm;padding:1.6mm 1mm}'
            .'.etat .nom{text-align:left;font-weight:bold;text-transform:uppercase}'
            .'.etat .num{text-align:right}'
            // Colonne laissée vide : c'est là que l'agent signe à réception.
            .'.signature{height:9mm}'
            .'.etat tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold}';
    }

    /** @param  Collection<int, BulletinPaie>  $bulletins */
    private function titre(string $periode, Collection $bulletins): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">État d\'émargement de la paie</span><br>'
            .'<span class="titre-en">Payroll acknowledgement sheet</span>'
            .'</div>'
            .'<div style="background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0;">'
            .'Période <i>/ Period</i> : '.$this->e($periode)
            .' &nbsp;|&nbsp; Effectif <i>/ Headcount</i> : '.$bulletins->count()
            .'</div>';
    }

    /** @param  Collection<int, BulletinPaie>  $bulletins */
    private function tableau(Collection $bulletins): string
    {
        $html = '<table class="etat"><thead><tr>'
            .'<th style="width:5%;">N°</th>'
            .'<th class="nom" style="width:24%;">Nom et prénoms<br><i>Full name</i></th>'
            .'<th style="width:11%;">Matricule</th>'
            .'<th style="width:13%;">Fonction<br><i>Position</i></th>'
            .'<th style="width:12%;">Net à percevoir<br><i>Net pay</i></th>'
            .'<th style="width:10%;">Mode<br><i>Method</i></th>'
            .'<th style="width:10%;">Date</th>'
            .'<th style="width:15%;">Signature<br><i>Signature</i></th>'
            .'</tr></thead><tbody>';

        $rang = 1;

        foreach ($bulletins as $bulletin) {
            $agent = $bulletin->personnel;

            $html .= '<tr>'
                .'<td>'.$rang++.'</td>'
                .'<td class="nom">'.$this->e($agent->nom_complet).'</td>'
                .'<td>'.$this->e($agent->matricule ?: '—').'</td>'
                .'<td>'.$this->e($agent->fonction ?: '—').'</td>'
                .'<td class="num">'.$this->francs($bulletin->net_a_payer).'</td>'
                .'<td>'.$this->e($this->modeLibelle($bulletin->mode_paiement)).'</td>'
                .'<td>'.($bulletin->date_paiement?->format('d/m/Y') ?? '').'</td>'
                .'<td class="signature"></td>'
                .'</tr>';
        }

        if ($bulletins->isEmpty()) {
            return $html.'<tr><td colspan="8" style="padding:6mm;">Aucun bulletin arrêté pour cette période.</td></tr>'
                .'</tbody></table>';
        }

        return $html.'<tr class="total">'
            .'<td colspan="4" class="nom">Total à décaisser <i>/ Total disbursed</i></td>'
            .'<td class="num">'.$this->francs((int) $bulletins->sum('net_a_payer')).'</td>'
            .'<td colspan="3"></td>'
            .'</tr></tbody></table>';
    }

    private function pied(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;font-size:2.8mm;vertical-align:top;">'
            .'Arrêté le présent état à la somme portée en total.<br>'
            .'<i>Closed at the total amount shown above.</i><br><br>'
            .'Fait à '.$this->e($ville !== '' ? $ville : '…………').', le '.date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i><br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            .'</td></tr></table>';
    }

    private function modeLibelle(?string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile Money',
            'virement' => 'Virement',
            'cheque' => 'Chèque',
            'depot_bancaire' => 'Dépôt banc.',
            'especes' => 'Espèces',
            default => '',
        };
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
