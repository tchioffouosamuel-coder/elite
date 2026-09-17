<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\NombreEnLettresAnglais;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Bordereau de virement des salaires — la lettre qui part effectivement à la
 * banque, au format des ordres de virement déjà émis par l'établissement
 * (courrier en anglais, un bloc par banque : adresse au responsable
 * d'agence, tableau nominatif, montant total en toutes lettres, signature).
 *
 * Un bloc par établissement bancaire, chacun avec son total : c'est ainsi que
 * la banque le reçoit. Chaque bloc commence sur une page neuve, parce qu'on
 * n'envoie pas à une banque la liste des agents domiciliés chez une autre.
 *
 * Les agents sans domiciliation ferment le document, hors total : ils doivent
 * être payés autrement, et l'oublier serait pire que de le voir écrit.
 */
class BordereauVirementGenerator
{
    use RenduDocument;

    private const MOIS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    private const JOURS = [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
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
        $mpdf->SetTitle('Salary transfer order — '.$periode);

        $blocs = [];

        foreach ($bordereau['banques'] as $banque) {
            $blocs[] = $this->enTeteEcole($school)
                .$this->lettre($school, $banque, $bordereau['periode'], $bordereau['numero_document']);
        }

        if ($bordereau['sans_domiciliation'] !== []) {
            $blocs[] = $this->enTeteEcole($school).$this->sansDomiciliation($bordereau['sans_domiciliation'], $periode);
        }

        if ($blocs === []) {
            $blocs[] = $this->enTeteEcole($school)
                .'<div class="section">Salary transfer order — '.$this->e($periode).'</div>'
                .'<p class="note">No bulletin arrêté pour cette période. / No finalized payslip for this period.</p>';
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
            .'.bv .civ{width:14mm}'
            .'.bv .lib{text-align:left}'
            .'.bv .cpt{text-align:left;font-family:dejavusansmono,monospace;font-size:2.5mm}'
            .'.bv .num{text-align:right;width:30mm;font-weight:bold}'
            .'.bv tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold;font-size:3mm}'
            .'.section{font-weight:bold;font-size:3.2mm;color:'.self::ARDOISE.';margin:3mm 0 1.5mm;text-transform:uppercase}'
            .'.note{font-size:2.4mm;color:#555;text-align:left;line-height:1.5}'
            .'.avertissement{border:0.6px solid #ac3527;color:#ac3527;padding:2mm;font-size:2.5mm;text-align:left}'
            .'.lettre-date{text-align:right;font-size:2.6mm;margin:2mm 0}'
            .'.lettre-titre{font-weight:bold;font-size:3mm;text-align:center;margin:2mm 0 3mm}'
            .'.lettre-corps{font-size:2.7mm;line-height:1.5;margin-bottom:2mm}';
    }

    /**
     * Une banque = une lettre : la date d'émission, l'objet numéroté, le
     * paragraphe d'adresse au responsable d'agence (compte de l'établissement
     * débité, en tête), le tableau nominatif, le total en toutes lettres, la
     * signature.
     *
     * @param  array<string, mixed>  $banque
     * @param  array{annee: int, mois: int}  $periode
     */
    private function lettre(School $school, array $banque, array $periode, string $numeroDocument): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '') ?: 'Bertoua';
        $moisLibelle = self::MOIS[$periode['mois']] ?? '';
        $compteEcole = $banque['numero_compte_ecole'] ?? '…………';

        return '<div class="lettre-date">'.$this->e($this->dateDuJour()).'</div>'
            .'<div class="lettre-titre">'.$this->e($moisLibelle).' '.$periode['annee']
            .' staff salaries transfer order N° '.$this->e($numeroDocument).'/'.sprintf('%02d', $periode['mois']).'/'.$periode['annee'].'</div>'
            .'<div class="lettre-corps">For the attention of '.$this->e($banque['banque']).' branch manager '.$this->e($ville)
            .' to credit the following staff account from our account main N° <b>'.$this->e($compteEcole).'</b>:</div>'
            .$this->tableau($banque['lignes'], (int) $banque['total'], (int) $banque['effectif'])
            .'<div class="lettre-corps" style="margin-top:2mm;">Total amount: <b>'
            .ucfirst(NombreEnLettresAnglais::convertir((int) $banque['total'])).' francs CFA</b>.</div>'
            .$this->signatureFondateur();
    }

    /** Aujourd'hui, en toutes lettres : « Tuesday 30th June 2026 », comme les ordres déjà émis par l'établissement. */
    private function dateDuJour(): string
    {
        $jour = (int) date('j');
        $suffixe = match (true) {
            $jour % 10 === 1 && $jour !== 11 => 'st',
            $jour % 10 === 2 && $jour !== 12 => 'nd',
            $jour % 10 === 3 && $jour !== 13 => 'rd',
            default => 'th',
        };

        return self::JOURS[(int) date('w')].' '.$jour.$suffixe.' '.self::MOIS[(int) date('n')].' '.date('Y');
    }

    private function signatureFondateur(): string
    {
        return '<table class="no-border" style="margin-top:10mm;"><tr>'
            .'<td class="no-border" style="width:100%;text-align:right;font-size:2.8mm;">'
            .'<b>The Founder</b><br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;">Signature and stamp</span>'
            .'</td></tr></table>';
    }

    /** @param list<array<string, mixed>> $lignes */
    private function tableau(array $lignes, int $total, int $effectif): string
    {
        $corps = '';

        foreach ($lignes as $ligne) {
            $corps .= '<tr>'
                .'<td class="civ">'.$this->e($ligne['civilite'] ?? '').'</td>'
                .'<td class="lib">'.$this->e($ligne['nom_complet'] ?? '—').'</td>'
                .'<td class="lib">'.$this->e($ligne['banque'] ?? '—').'</td>'
                .'<td class="cpt">'.$this->e($ligne['numero_compte'] ?? '—').'</td>'
                .'<td class="num">'.$this->francs((int) $ligne['montant']).'</td>'
                .'</tr>';
        }

        return '<table class="bv"><thead><tr>'
            .'<th class="civ"></th><th>Names</th><th>Bank</th>'
            .'<th style="width:40mm">Account N°</th><th style="width:30mm">Amount</th>'
            .'</tr></thead><tbody>'.$corps.'</tbody>'
            .'<tfoot><tr class="total"><td colspan="4" class="lib">Total — '.$effectif.' staff</td>'
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
