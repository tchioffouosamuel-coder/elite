<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Bordereau de virement des salaires — le document qui part à la banque.
 *
 * Un bloc par établissement bancaire, chacun avec son total : c'est ainsi que
 * la banque le reçoit, et ainsi que le classeur le tient. Chaque bloc commence
 * sur une page neuve, parce qu'on n'envoie pas à une banque la liste des
 * agents domiciliés chez une autre.
 *
 * Les agents sans domiciliation ferment le document, hors total : ils doivent
 * être payés autrement, et l'oublier serait pire que de le voir écrit.
 */
class BordereauVirementGenerator
{
    use RenduDocument;

    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    /** @param array<string, mixed> $bordereau */
    public function build(School $school, array $bordereau): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);

        $periode = $this->periode($bordereau['periode']);
        $mpdf->SetTitle('Bordereau de virement — '.$periode);

        $blocs = [];

        foreach ($bordereau['banques'] as $banque) {
            $blocs[] = $this->enTeteEcole($school)
                .$this->titre($banque['banque'], $periode)
                .$this->tableau($banque['lignes'], (int) $banque['total'], (int) $banque['effectif'])
                .$this->signatureChef($school);
        }

        if ($bordereau['sans_domiciliation'] !== []) {
            $blocs[] = $this->enTeteEcole($school).$this->sansDomiciliation($bordereau['sans_domiciliation'], $periode);
        }

        if ($blocs === []) {
            $blocs[] = $this->enTeteEcole($school)
                .'<div class="section">Bordereau de virement — '.$this->e($periode).'</div>'
                .'<p class="note">Aucun bulletin arrêté pour cette période.</p>';
        }

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .implode('<pagebreak />', $blocs)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bv td{font-size:2.7mm;padding:1.4mm}'
            .'.bv th{font-size:2.5mm}'
            .'.bv .rang{width:10mm}'
            .'.bv .lib{text-align:left}'
            .'.bv .cpt{text-align:left;font-family:dejavusansmono,monospace;font-size:2.5mm}'
            .'.bv .num{text-align:right;width:30mm;font-weight:bold}'
            .'.bv tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold;font-size:3mm}'
            .'.section{font-weight:bold;font-size:3.2mm;color:'.self::ARDOISE.';margin:3mm 0 1.5mm;text-transform:uppercase}'
            .'.banque{font-size:4mm;font-weight:bold;color:'.self::ACCENT.';text-transform:uppercase}'
            .'.note{font-size:2.4mm;color:#555;text-align:left;line-height:1.5}'
            .'.avertissement{border:0.6px solid #ac3527;color:#ac3527;padding:2mm;font-size:2.5mm;text-align:left}';
    }

    private function titre(string $banque, string $periode): string
    {
        return '<div style="text-align:center;margin-bottom:2mm">'
            .'<span class="titre">Demande de virement des salaires</span><br>'
            .'<span class="titre-en">Salary transfer order</span><br>'
            .'<span class="banque">'.$this->e($banque).'</span><br>'
            .'<span class="mini">Période : '.$this->e($periode).'</span>'
            .'</div>';
    }

    /** @param list<array<string, mixed>> $lignes */
    private function tableau(array $lignes, int $total, int $effectif): string
    {
        $corps = '';
        $rang = 0;

        foreach ($lignes as $ligne) {
            $corps .= '<tr>'
                .'<td class="rang">'.(++$rang).'</td>'
                .'<td class="lib">'.$this->e($ligne['nom_complet'] ?? '—').'</td>'
                .'<td class="cpt">'.$this->e($ligne['numero_compte'] ?? '—').'</td>'
                .'<td class="num">'.$this->francs((int) $ligne['montant']).'</td>'
                .'</tr>';
        }

        return '<table class="bv"><thead><tr>'
            .'<th class="rang">N°</th><th>Nom et prénoms</th>'
            .'<th style="width:45mm">N° de compte</th><th style="width:30mm">Montant</th>'
            .'</tr></thead><tbody>'.$corps.'</tbody>'
            .'<tfoot><tr class="total"><td colspan="3" class="lib">Total — '.$effectif.' agent(s)</td>'
            .'<td class="num">'.$this->francs($total).'</td></tr></tfoot></table>';
    }

    /** @param list<array<string, mixed>> $lignes */
    private function sansDomiciliation(array $lignes, string $periode): string
    {
        $corps = '';

        foreach ($lignes as $ligne) {
            $corps .= '<tr>'
                .'<td class="lib">'.$this->e($ligne['nom_complet'] ?? '—').'</td>'
                .'<td class="cpt">'.$this->e($ligne['matricule'] ?? '—').'</td>'
                .'<td class="num">'.$this->francs((int) $ligne['net_a_payer']).'</td>'
                .'</tr>';
        }

        return '<div class="section">Agents non virables — '.$this->e($periode).'</div>'
            .'<div class="avertissement">Ces agents ne figurent sur aucun bordereau : leur banque ou leur numéro de '
            .'compte n\'est pas renseigné. Leur salaire doit être réglé par un autre moyen, et leur domiciliation '
            .'complétée avant la prochaine paie.</div>'
            .'<table class="bv"><thead><tr>'
            .'<th>Nom et prénoms</th><th style="width:45mm">Matricule</th><th style="width:30mm">Net à payer</th>'
            .'</tr></thead><tbody>'.$corps.'</tbody></table>';
    }

    /** @param array{annee: int, mois: int} $periode */
    private function periode(array $periode): string
    {
        return (self::MOIS[$periode['mois']] ?? '').' '.$periode['annee'];
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
