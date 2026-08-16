<?php

namespace App\Support\Pdf;

use App\Models\BulletinPaie;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Bulletin de paie bilingue, repris du modèle de l'établissement
 * (« BULLETIN DE PAIE / PAY SLIP »).
 *
 * Les lignes viennent du bulletin enregistré, jamais d'un recalcul : un
 * bulletin réédité trois ans plus tard doit afficher les taux du mois
 * concerné, pas ceux en vigueur au moment de la réimpression.
 */
class BulletinPaieGenerator
{
    use RenduDocument;

    public function build(BulletinPaie $bulletin): string
    {
        $bulletin->loadMissing(['lignes', 'personnel.fonctionReference', 'personnel.school']);
        $school = $bulletin->personnel->school;

        $mpdf = MpdfFactory::make(['format' => 'A4', 'margin_top' => 8, 'margin_bottom' => 10], $school);
        $mpdf->SetTitle('Bulletin de paie — '.$bulletin->personnel->nom_complet.' — '.$bulletin->periode_libelle);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$this->enTeteEcole($school)
                .$this->titre($bulletin)
                .$this->identite($bulletin)
                .$this->tableau($bulletin)
                .$this->cumuls($bulletin)
                .$this->pied($bulletin)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.paie th{font-size:2.3mm;padding:1mm 0.5mm}'
            .'.paie td{font-size:2.5mm;padding:1mm 1mm}'
            .'.paie .lib{text-align:left}'
            .'.paie .num{text-align:right}'
            .'.sous-total td{background-color:#f3ede0;font-weight:bold}'
            .'.brut td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold}'
            .'.net td{background-color:'.self::ACCENT.';color:#fff;font-weight:bold;font-size:3.2mm}'
            .'.identite td{font-size:2.5mm;padding:0.8mm 1mm}'
            .'.identite .k{font-weight:bold;width:24%}';
    }

    private function titre(BulletinPaie $bulletin): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Bulletin de paie</span><br>'
            .'<span class="titre-en">Pay slip</span>'
            .'</div>'
            .'<div class="bandeau" style="background:'.self::ARDOISE.';color:#fff;padding:1.5mm;text-align:center;'
            .'font-size:2.8mm;font-weight:bold;margin:2mm 0;">'
            .'Période <i>/ Period</i> : '.$bulletin->periode_debut->format('d/m/Y')
            .' — '.$bulletin->periode_fin->format('d/m/Y')
            .' &nbsp;|&nbsp; N° '.$this->e($bulletin->numero)
            .'</div>';
    }

    private function identite(BulletinPaie $bulletin): string
    {
        $agent = $bulletin->personnel;

        $gauche = [
            ['Nom / Name', mb_strtoupper($agent->nom_complet)],
            ['Matricule', $agent->matricule ?: '—'],
            ['N° CNPS', $agent->numero_cnps ?: '—'],
            ['Fonction / Position', $agent->fonction ?: '—'],
        ];

        $droite = [
            ['Affectation / Post', $agent->affectation ?: '—'],
            ['Situation', $this->situation($agent->situation_matrimoniale)],
            ['Enfants / Children', (string) ($agent->nombre_enfants ?? 0)],
            ['Embauche / Hired', $agent->date_embauche?->format('d/m/Y') ?? '—'],
        ];

        return '<table class="no-border"><tr>'
            .'<td class="no-border" style="width:50%;vertical-align:top;">'.$this->colonneIdentite($gauche).'</td>'
            .'<td class="no-border" style="width:50%;vertical-align:top;">'.$this->colonneIdentite($droite).'</td>'
            .'</tr></table>';
    }

    /** @param  list<array{0: string, 1: string}>  $lignes */
    private function colonneIdentite(array $lignes): string
    {
        $html = '<table class="identite no-border">';

        foreach ($lignes as [$cle, $valeur]) {
            $html .= '<tr><td class="no-border k">'.$this->e($cle).'</td>'
                .'<td class="no-border">'.$this->e($valeur).'</td></tr>';
        }

        return $html.'</table>';
    }

    private function situation(?string $code): string
    {
        return match ($code) {
            'marie' => 'Marié(e)',
            'divorce' => 'Divorcé(e)',
            'veuf' => 'Veuf / Veuve',
            'celibataire' => 'Célibataire',
            default => '—',
        };
    }

    private function tableau(BulletinPaie $bulletin): string
    {
        $html = '<table class="paie"><thead><tr>'
            .'<th style="width:4%;">N°</th>'
            .'<th class="lib" style="width:34%;">Désignation<br><i>Description</i></th>'
            .'<th style="width:14%;">Base<br><i>Basic</i></th>'
            .'<th style="width:9%;">Taux<br><i>Rate</i></th>'
            .'<th style="width:13%;">Gain<br><i>Earning</i></th>'
            .'<th style="width:13%;">Retenue<br><i>Deduction</i></th>'
            .'<th style="width:13%;">Part patronale<br><i>Employer</i></th>'
            .'</tr></thead><tbody>';

        $rang = 1;

        foreach ($bulletin->lignes->where('type', 'gain') as $ligne) {
            $html .= '<tr>'
                .'<td>'.$rang++.'</td>'
                .'<td class="lib">'.$this->e($ligne->libelle).' <i>/ '.$this->e($ligne->libelle_en).'</i></td>'
                .'<td></td><td></td>'
                .'<td class="num">'.$this->francs($ligne->montant_salarial).'</td>'
                .'<td></td><td></td>'
                .'</tr>';
        }

        $html .= '<tr class="brut"><td colspan="4" class="lib">Salaire brut <i>/ Gross salary</i></td>'
            .'<td class="num">'.$this->francs($bulletin->salaire_brut).'</td><td></td><td></td></tr>';

        foreach ($bulletin->lignes->where('type', 'retenue') as $ligne) {
            $html .= '<tr>'
                .'<td>'.$rang++.'</td>'
                .'<td class="lib">'.$this->e($ligne->libelle).' <i>/ '.$this->e($ligne->libelle_en).'</i></td>'
                .'<td class="num">'.$this->francs((int) $ligne->base).'</td>'
                .'<td class="num">'.$this->taux($ligne->taux_salarial).'</td>'
                .'<td></td>'
                .'<td class="num">'.$this->francs((int) $ligne->montant_salarial).'</td>'
                .'<td class="num">'.$this->francs((int) $ligne->montant_patronal).'</td>'
                .'</tr>';
        }

        return $html.'<tr class="sous-total">'
            .'<td colspan="5" class="lib">Total des cotisations <i>/ Total contributions</i></td>'
            .'<td class="num">'.$this->francs($bulletin->charges_salariales).'</td>'
            .'<td class="num">'.$this->francs($bulletin->charges_patronales).'</td>'
            .'</tr></tbody></table>';
    }

    private function cumuls(BulletinPaie $bulletin): string
    {
        $deductions = [
            ['Absences', $bulletin->deduction_absences],
            ['Raff', $bulletin->deduction_raff],
            ['Njangi', $bulletin->deduction_njangi],
            ['Prêt / Loan', $bulletin->deduction_pret],
            ['Autre / Other', $bulletin->deduction_autre],
        ];

        $html = '<table class="paie"><thead><tr>'
            .'<th>Jours ouvrables<br><i>Working days</i></th>'
            .'<th>Jours travaillés<br><i>Days worked</i></th>'
            .'<th>Net taxable<br><i>Taxable</i></th>';

        foreach ($deductions as [$libelle]) {
            $html .= '<th>'.$this->e($libelle).'</th>';
        }

        $html .= '</tr></thead><tbody><tr>'
            .'<td>'.$bulletin->jours_ouvrables.'</td>'
            .'<td>'.$bulletin->jours_travailles.'</td>'
            .'<td class="num">'.$this->francs($bulletin->net_taxable).'</td>';

        foreach ($deductions as [, $montant]) {
            $html .= '<td class="num">'.$this->francs((int) $montant).'</td>';
        }

        return $html.'</tr></tbody></table>'
            .'<table class="paie"><tr class="net">'
            .'<td class="lib">Net à percevoir <i>/ Net pay</i></td>'
            .'<td class="num">'.$this->francs($bulletin->net_a_payer).'</td>'
            .'</tr></table>';
    }

    private function pied(BulletinPaie $bulletin): string
    {
        $reglement = $bulletin->date_paiement
            ? 'Réglé le '.$bulletin->date_paiement->format('d/m/Y').' par '.$this->modeLibelle($bulletin->mode_paiement)
            : 'Non réglé à ce jour <i>/ Not yet paid</i>';

        return '<table class="no-border" style="margin-top:5mm;"><tr>'
            .'<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.6mm;">'
            .$reglement.'<br><br>'
            .'<b>Signature de l\'agent</b><br><i>Employee signature</i>'
            .'<br><br><br><span style="border-top:0.4px solid #000;">Pour acquit / Received</span>'
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.6mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i><br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            .'</td></tr></table>'
            .'<div class="legende" style="margin-top:3mm;">'
            .'Ce bulletin est à conserver sans limitation de durée. <i>Keep this pay slip indefinitely.</i>'
            .'</div>';
    }

    private function modeLibelle(?string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'Mobile Money',
            'virement' => 'virement',
            'cheque' => 'chèque',
            'depot_bancaire' => 'dépôt bancaire',
            default => 'caisse',
        };
    }

    private function taux(?float $taux): string
    {
        return $taux === null ? '' : rtrim(rtrim(number_format($taux, 2, ',', ' '), '0'), ',').' %';
    }

    private function francs(int $montant): string
    {
        return $montant === 0 ? '—' : number_format($montant, 0, ',', ' ');
    }
}
