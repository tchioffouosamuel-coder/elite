<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Liste des élèves ayant bénéficié d'une remise sur la scolarité — le motif
 * et l'auteur figurent en colonnes pour que le document serve aussi de pièce
 * justificative en cas de contrôle, pas seulement de récapitulatif chiffré.
 */
class RemisesGenerator
{
    use RenduDocument;

    /**
     * @param  Collection<int, \App\Models\Remise>  $remises
     */
    public function build(?School $school, Collection $remises): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4-L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle('Liste des remises accordées');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<style>' . $this->stylesBase() . $this->stylesPropres() . '</style></head><body>'
                . ($school ? $this->enTeteEcole($school) . '<hr>' : '')
                . $this->titre($remises)
                . $this->tableau($remises)
                . ($school ? $this->signature($school) : '')
                . '</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:' . self::ARDOISE . ';color:#fff;padding:2mm;text-align:center;'
            . 'font-size:3mm;font-weight:bold;margin:3mm 0}'
            . '.liste th{font-size:2.5mm}'
            . '.liste td{font-size:2.5mm;padding:1.2mm 1mm}'
            . '.liste tbody tr:nth-child(even) td{background:#f7f7f5}'
            . '.nom{font-weight:bold;text-transform:uppercase}';
    }

    private function titre(Collection $remises): string
    {
        $total = (int) $remises->sum('montant');

        return '<div style="text-align:center;line-height:1.4;">'
            . '<span class="titre">Liste des remises accordées</span><br>'
            . '<span class="titre-en">Discounts granted</span>'
            . '</div>'
            . '<div class="bandeau">'
            . 'Effectif <i>/ Headcount</i> : ' . $remises->pluck('eleve_id')->unique()->count()
            . ' &nbsp;|&nbsp; Nombre de remises <i>/ Discounts</i> : ' . $remises->count()
            . ' &nbsp;|&nbsp; Total remisé <i>/ Total discounted</i> : ' . number_format($total, 0, ',', ' ') . ' F'
            . '</div>';
    }

    private function tableau(Collection $remises): string
    {
        $corps = '';
        $rang = 1;

        foreach ($remises as $remise) {
            $eleve = $remise->eleve;

            $corps .= '<tr>'
                . '<td>' . $rang . '</td>'
                . '<td class="left nom">' . $this->e($eleve?->nom_complet ?? '—') . '</td>'
                . '<td>' . $this->e($eleve?->matricule ?: '—') . '</td>'
                . '<td class="left">' . $this->e($eleve?->classe?->nom ?: '—') . '</td>'
                . '<td>' . number_format((int) $remise->montant, 0, ',', ' ') . '</td>'
                . '<td class="left">' . $this->e($remise->motif ?: '—') . '</td>'
                . '<td class="left">' . $this->e($remise->accordePar?->name ?: '—') . '</td>'
                . '<td>' . $this->e($this->formaterDate($remise->created_at)) . '</td>'
                . '</tr>';
            $rang++;
        }

        if ($corps === '') {
            $corps = '<tr><td colspan="8" style="padding:6mm;">Aucune remise accordée sur ce périmètre.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'
            . '<th style="width:4%;">N°</th>'
            . '<th style="width:18%;">Élève<br><i>Student</i></th>'
            . '<th style="width:10%;">Matricule<br><i>ID</i></th>'
            . '<th style="width:12%;">Classe<br><i>Class</i></th>'
            . '<th style="width:10%;">Remise (F)<br><i>Discount</i></th>'
            . '<th style="width:26%;">Motif<br><i>Reason</i></th>'
            . '<th style="width:14%;">Accordée par<br><i>Granted by</i></th>'
            . '<th style="width:10%;">Date<br><i>Date</i></th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table>';
    }

    private function formaterDate($date): string
    {
        return $date ? $date->format('d/m/Y') : '—';
    }

    private function signature(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $lieu = $ville !== '' ? 'Fait à ' . $ville . ', le ' : 'Fait le ';

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            . '<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.8mm;">'
            . $this->e($lieu) . date('d/m/Y')
            . '</td>'
            . '<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            . '<b>Le Chef d\'Établissement</b><br><i>The Principal</i>'
            . '<br><br><br><br>'
            . '<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature et cachet</span>'
            . '</td></tr></table>';
    }
}
