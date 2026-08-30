<?php

namespace App\Support\Pdf;

use App\Models\BudgetPersonnel;
use App\Models\Depense;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Bilan de gestion d'un budget alloué à un membre du personnel : ce qui a été
 * donné, ce qu'il en a dépensé, ce qu'il en reste, et comment il explique
 * lui-même sa gestion — la pièce que la hiérarchie et l'intéressé se
 * partagent pour faire le point sur l'enveloppe.
 */
class BudgetPersonnelBilanGenerator
{
    use RenduDocument;

    /**
     * @param  array{alloue: int, depense: int, solde: int, statut: string, depenses: Collection<int, Depense>}  $bilan
     */
    public function build(BudgetPersonnel $budget, array $bilan, ?string $du, ?string $au): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $budget->school);
        $mpdf->SetTitle('Bilan de budget — ' . $budget->personnel?->nom_complet);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                . $this->stylesBase() . $this->stylesPropres()
                . '</style></head><body>'
                . $this->enTeteEcole($budget->school)
                . $this->titre($budget, $du, $au)
                . $this->resultat($bilan)
                . $this->noteGestion($budget)
                . $this->depenses($bilan['depenses'])
                . $this->signature($budget)
                . '</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.dep th{font-size:2.4mm}'
            . '.dep td{font-size:2.5mm;padding:1.2mm 1mm}'
            . '.dep .lib{text-align:left}'
            . '.dep .num{text-align:right}'
            . '.dep tbody tr:nth-child(even) td{background:#f7f7f5}'
            . '.total td{background-color:' . self::ARDOISE . ';color:#fff;font-weight:bold}'
            . '.annulee td{color:#999;text-decoration:line-through}'
            . '.section{font-weight:bold;font-size:3mm;color:' . self::ARDOISE . ';margin:4mm 0 1mm}'
            . '.resultat{padding:3mm;text-align:center;font-size:3.4mm;font-weight:bold;margin:3mm 0;border-radius:2mm}'
            . '.actif{background:#e3f3e8;color:#1d7a4c}'
            . '.epuise{background:#fbe4e0;color:#a33a2d}'
            . '.annule{background:#eceff1;color:#546e7a}'
            . '.note{padding:2.5mm 3mm;font-size:2.8mm;margin:3mm 0;border-left:1mm solid ' . self::ARDOISE . ';background:#f7f7f5;white-space:pre-line;}';
    }

    private function titre(BudgetPersonnel $budget, ?string $du, ?string $au): string
    {
        $periode = $du || $au
            ? 'du ' . ($du ? $this->jour($du) : '…') . ' au ' . ($au ? $this->jour($au) : $this->jour(date('Y-m-d')))
            : 'depuis l\'allocation';

        return '<div style="text-align:center;line-height:1.4;">'
            . '<span class="titre">Bilan de gestion de budget</span><br>'
            . '<span class="titre-en">Budget management statement</span>'
            . '</div>'
            . '<div style="background:' . self::ARDOISE . ';color:#fff;padding:2mm;text-align:center;'
            . 'font-size:2.9mm;font-weight:bold;margin:3mm 0;">'
            . 'Personnel : ' . $this->e($budget->personnel?->nom_complet ?: '—')
            . ' &nbsp;|&nbsp; Budget : ' . $this->e($budget->libelle)
            . ' &nbsp;|&nbsp; Période <i>/ Period</i> : ' . $this->e($periode)
            . '</div>';
    }

    /** @param array{alloue: int, depense: int, solde: int, statut: string} $bilan */
    private function resultat(array $bilan): string
    {
        $classe = match ($bilan['statut']) {
            'annule' => 'annule',
            'epuise' => 'epuise',
            default => 'actif',
        };

        return '<table class="no-border"><tr>'
            . '<td class="no-border" style="width:33%;text-align:center;">Alloué<br><b style="font-size:4mm;">' . $this->francs($bilan['alloue']) . ' F</b></td>'
            . '<td class="no-border" style="width:33%;text-align:center;">Dépensé<br><b style="font-size:4mm;">' . $this->francs($bilan['depense']) . ' F</b></td>'
            . '<td class="no-border" style="width:34%;text-align:center;">Solde restant<br><b style="font-size:4mm;">' . $this->francs($bilan['solde']) . ' F</b></td>'
            . '</tr></table>'
            . '<div class="resultat ' . $classe . '">'
            . match ($classe) {
                'annule' => 'Budget clôturé',
                'epuise' => 'Budget entièrement consommé',
                default => 'Budget actif',
            }
            . '</div>';
    }

    private function noteGestion(BudgetPersonnel $budget): string
    {
        $texte = trim((string) $budget->note_gestion);

        return '<div class="section">Note de gestion — comment le budget est géré</div>'
            . '<div class="note">' . ($texte !== '' ? nl2br($this->e($texte)) : 'Aucune note renseignée par le personnel concerné.') . '</div>';
    }

    /** @param Collection<int, Depense> $depenses */
    private function depenses(Collection $depenses): string
    {
        $html = '<div class="section">Dépenses imputées sur ce budget</div>'
            . '<table class="dep"><thead><tr>'
            . '<th style="width:12%;">Date</th>'
            . '<th class="lib" style="width:40%;">Libellé</th>'
            . '<th style="width:20%;">Compte</th>'
            . '<th style="width:14%;">État</th>'
            . '<th style="width:14%;">Montant</th>'
            . '</tr></thead><tbody>';

        if ($depenses->isEmpty()) {
            return $html . '<tr><td colspan="5" style="padding:6mm;">Aucune dépense imputée sur ce budget pour la période.</td></tr></tbody></table>';
        }

        foreach ($depenses as $depense) {
            $annulee = $depense->statut === 'annulee';

            $html .= '<tr' . ($annulee ? ' class="annulee"' : '') . '>'
                . '<td>' . $depense->date_depense?->format('d/m/Y') . '</td>'
                . '<td class="lib">' . $this->e($depense->libelle) . '</td>'
                . '<td>' . $this->e($depense->compte?->code ?: '—') . '</td>'
                . '<td>' . $this->e($depense->statut === 'engagee' ? 'Engagée' : ($annulee ? 'Annulée' : 'Payée')) . '</td>'
                . '<td class="num">' . $this->francs($depense->montant) . '</td>'
                . '</tr>';
        }

        $retenu = (int) $depenses->where('statut', '!=', 'annulee')->sum('montant');

        return $html . '<tr class="total">'
            . '<td colspan="4" class="lib">Total des dépenses</td>'
            . '<td class="num">' . $this->francs($retenu) . ' F</td>'
            . '</tr></tbody></table>';
    }

    private function signature(BudgetPersonnel $budget): string
    {
        $ville = trim(explode(',', (string) $budget->school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            . '<td class="no-border left" style="width:50%;font-size:2.8mm;vertical-align:top;">'
            . 'Fait à ' . $this->e($ville !== '' ? $ville : '…………') . ', le ' . date('d/m/Y')
            . '</td>'
            . '<td class="no-border" style="width:25%;text-align:center;font-size:2.8mm;">'
            . '<b>' . $this->e($budget->personnel?->nom_complet ?: '') . '</b><br><i>Concerné</i><br><br><br><br>'
            . '<span style="border-top:0.4px solid #000;">Signature</span>'
            . '</td>'
            . '<td class="no-border" style="width:25%;text-align:center;font-size:2.8mm;">'
            . '<b>Le Chef d\'Établissement</b><br><i>The Principal</i><br><br><br><br>'
            . '<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            . '</td></tr></table>';
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
