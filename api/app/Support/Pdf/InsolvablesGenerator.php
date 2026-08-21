<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Liste des insolvables, montants détaillés par rubrique — l'école
 * (mode agrégé du super admin) et la classe apparaissent en colonnes plutôt
 * qu'en filtre imprimé séparément, pour qu'un seul document couvre tout le
 * périmètre demandé.
 */
class InsolvablesGenerator
{
    use RenduDocument;

    /**
     * @param  Collection<int, array{eleve: array, school: array, total_du: int, total_paye: int, reste_a_payer: int, rubriques: array, moratoire: ?array}>  $lignes
     * @param  array{effectif: int, total_du: int, total_reste: int}  $totaux
     */
    public function build(?School $school, Collection $lignes, array $totaux): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4-L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle('Liste des insolvables');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<style>' . $this->stylesBase() . $this->stylesPropres() . '</style></head><body>'
                . ($school ? $this->enTeteEcole($school) . '<hr>' : '')
                . $this->titre($totaux)
                . $this->tableau($lignes)
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
            . '.nom{font-weight:bold;text-transform:uppercase}'
            . '.rubrique{display:block;font-size:2.2mm;color:#555;text-align:left}'
            . '.moratoire{display:block;font-size:2.1mm;color:' . self::ACCENT . ';font-weight:bold;margin-top:1mm}';
    }

    private function titre(array $totaux): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            . '<span class="titre">Liste des insolvables</span><br>'
            . '<span class="titre-en">Defaulting students</span>'
            . '</div>'
            . '<div class="bandeau">'
            . 'Effectif <i>/ Headcount</i> : ' . $totaux['effectif']
            . ' &nbsp;|&nbsp; Total dû <i>/ Total due</i> : ' . number_format($totaux['total_du'], 0, ',', ' ') . ' F'
            . ' &nbsp;|&nbsp; Reste à recouvrer <i>/ Outstanding</i> : ' . number_format($totaux['total_reste'], 0, ',', ' ') . ' F'
            . '</div>';
    }

    private function tableau(Collection $lignes): string
    {
        $corps = '';
        $rang = 1;

        foreach ($lignes as $ligne) {
            $rubriques = collect($ligne['rubriques'] ?? [])
                ->filter(fn (array $r) => $r['reste'] > 0)
                ->map(fn (array $r) => '<span class="rubrique">' . $this->e($r['libelle']) . ' : ' . number_format($r['reste'], 0, ',', ' ') . ' F</span>')
                ->implode('');

            $moratoire = $ligne['moratoire']
                ? '<span class="moratoire">Moratoire jusqu\'au ' . $this->e($this->formaterDate($ligne['moratoire']['date_expiration'])) . '</span>'
                : '';

            $corps .= '<tr>'
                . '<td>' . $rang . '</td>'
                . '<td class="left">' . $this->e($ligne['school']['name']) . '</td>'
                . '<td class="left nom">' . $this->e($ligne['eleve']['nom_complet']) . '</td>'
                . '<td>' . $this->e($ligne['eleve']['matricule'] ?: '—') . '</td>'
                . '<td class="left">' . $this->e($ligne['eleve']['classe'] ?: '—') . '</td>'
                . '<td class="left">' . $rubriques . $moratoire . '</td>'
                . '<td>' . number_format($ligne['reste_a_payer'], 0, ',', ' ') . '</td>'
                . '</tr>';
            $rang++;
        }

        if ($corps === '') {
            $corps = '<tr><td colspan="7" style="padding:6mm;">Aucun élève insolvable sur ce périmètre.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'
            . '<th style="width:4%;">N°</th>'
            . '<th style="width:16%;">École<br><i>School</i></th>'
            . '<th style="width:18%;">Élève<br><i>Student</i></th>'
            . '<th style="width:10%;">Matricule<br><i>ID</i></th>'
            . '<th style="width:12%;">Classe<br><i>Class</i></th>'
            . '<th style="width:32%;">Détail par rubrique<br><i>Breakdown</i></th>'
            . '<th style="width:8%;">Reste (F)<br><i>Balance</i></th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table>';
    }

    private function formaterDate(string $isoDate): string
    {
        return date('d/m/Y', strtotime($isoDate));
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
            . $this->visa($school)
            . '<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature et cachet</span>'
            . '</td></tr></table>';
    }

    private function visa(School $school): string
    {
        $visa = (new VisaComposeService)->chemin($school);

        return $visa !== null
            ? '<img src="' . $this->e($visa) . '" style="height:46px;">'
            : '<br><br><br><br>';
    }
}
