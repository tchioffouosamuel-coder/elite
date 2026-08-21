<?php

namespace App\Support\Pdf;

use App\Models\BusAffectation;
use App\Models\BusVehicule;
use App\Models\Depense;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Bilan financier d'un véhicule : ce qu'il a rapporté (souscriptions
 * effectivement réglées) contre ce qu'il a coûté (dépenses de flotte), et le
 * résultat qui en découle — la question à laquelle le complexe veut une
 * réponse nette : ce bus fait-il gagner ou perdre de l'argent.
 */
class BilanVehiculeGenerator
{
    use RenduDocument;

    /**
     * @param  array{
     *   recettes: int, depenses_total: int, benefice: int, deficitaire: bool,
     *   depenses: Collection<int, Depense>, affectations: Collection<int, BusAffectation>,
     * }  $bilan
     */
    public function build(BusVehicule $vehicule, array $bilan, ?string $du, ?string $au): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $vehicule->school);
        $mpdf->SetTitle('Bilan financier — '.$vehicule->immatriculation);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$this->enTeteEcole($vehicule->school)
                .$this->titre($vehicule, $du, $au)
                .$this->resultat($bilan)
                .$this->recettes($bilan)
                .$this->depenses($bilan['depenses'])
                .$this->conclusion($vehicule, $bilan)
                .$this->signature($vehicule)
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
            .'.section{font-weight:bold;font-size:3mm;color:'.self::ARDOISE.';margin:4mm 0 1mm}'
            .'.resultat{padding:3mm;text-align:center;font-size:3.4mm;font-weight:bold;margin:3mm 0;border-radius:2mm}'
            .'.profit{background:#e3f3e8;color:#1d7a4c}'
            .'.perte{background:#fbe4e0;color:#a33a2d}'
            .'.conclusion{padding:2.5mm 3mm;font-size:2.8mm;margin:3mm 0;border-left:1mm solid '.self::ARDOISE.';background:#f7f7f5}';
    }

    private function titre(BusVehicule $vehicule, ?string $du, ?string $au): string
    {
        $periode = $du || $au
            ? 'du '.($du ? $this->jour($du) : '…').' au '.($au ? $this->jour($au) : $this->jour(date('Y-m-d')))
            : 'depuis la mise en service';
        $identite = $vehicule->immatriculation.($vehicule->couleur ? ' — '.$vehicule->couleur : '').($vehicule->marque ? ' ('.$vehicule->marque.')' : '');

        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Bilan financier du véhicule</span><br>'
            .'<span class="titre-en">Vehicle financial statement</span>'
            .'</div>'
            .'<div style="background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:2.9mm;font-weight:bold;margin:3mm 0;">'
            .'Véhicule <i>/ Vehicle</i> : '.$this->e($identite)
            .' &nbsp;|&nbsp; Chauffeur : '.$this->e($vehicule->chauffeur?->nom_complet ?: '—')
            .' &nbsp;|&nbsp; Période <i>/ Period</i> : '.$this->e($periode)
            .'</div>';
    }

    /** @param array{recettes: int, depenses_total: int, benefice: int, deficitaire: bool} $bilan */
    private function resultat(array $bilan): string
    {
        $classe = $bilan['deficitaire'] ? 'perte' : 'profit';
        $libelle = $bilan['deficitaire'] ? 'Résultat déficitaire' : 'Résultat bénéficiaire';

        return '<div class="resultat '.$classe.'">'
            .$this->e($libelle).' : '.$this->francs($bilan['benefice']).' F'
            .'</div>';
    }

    /** @param array{recettes: int, affectations: Collection<int, BusAffectation>} $bilan */
    private function recettes(array $bilan): string
    {
        return '<div class="section">Recettes — souscriptions effectivement réglées</div>'
            .'<table class="dep"><thead><tr>'
            .'<th class="lib" style="width:60%;">Poste</th>'
            .'<th style="width:20%;">Élèves transportés</th>'
            .'<th style="width:20%;">Montant</th>'
            .'</tr></thead><tbody>'
            .'<tr class="total">'
            .'<td class="lib">Total des versements « transport » encaissés sur la période</td>'
            .'<td>'.$bilan['affectations']->count().'</td>'
            .'<td class="num">'.$this->francs($bilan['recettes']).' F</td>'
            .'</tr>'
            .'</tbody></table>';
    }

    /** @param Collection<int, Depense> $depenses */
    private function depenses(Collection $depenses): string
    {
        $html = '<div class="section">Dépenses — maintenance, entretien et frais du véhicule</div>'
            .'<table class="dep"><thead><tr>'
            .'<th style="width:8%;">Date</th>'
            .'<th class="lib" style="width:30%;">Libellé</th>'
            .'<th class="lib" style="width:18%;">Bénéficiaire</th>'
            .'<th style="width:12%;">Compte</th>'
            .'<th style="width:12%;">Facture</th>'
            .'<th style="width:10%;">État</th>'
            .'<th style="width:10%;">Montant</th>'
            .'</tr></thead><tbody>';

        if ($depenses->isEmpty()) {
            return $html.'<tr><td colspan="7" style="padding:6mm;">Aucune dépense enregistrée pour ce véhicule sur la période.</td></tr></tbody></table>';
        }

        foreach ($depenses as $depense) {
            $annulee = $depense->statut === 'annulee';

            $html .= '<tr'.($annulee ? ' class="annulee"' : '').'>'
                .'<td>'.$depense->date_depense?->format('d/m/Y').'</td>'
                .'<td class="lib">'.$this->e($depense->libelle).'</td>'
                .'<td class="lib">'.$this->e($depense->beneficiaire ?: '—').'</td>'
                .'<td>'.$this->e($depense->compte?->code ?: '—').'</td>'
                .'<td>'.$this->e($depense->reference_facture ?: '—').'</td>'
                .'<td>'.$this->e($depense->statut === 'engagee' ? 'Engagée' : ($annulee ? 'Annulée' : 'Payée')).'</td>'
                .'<td class="num">'.$this->francs($depense->montant).'</td>'
                .'</tr>';
        }

        $retenu = (int) $depenses->where('statut', '!=', 'annulee')->sum('montant');

        return $html.'<tr class="total">'
            .'<td colspan="6" class="lib">Total des dépenses</td>'
            .'<td class="num">'.$this->francs($retenu).' F</td>'
            .'</tr></tbody></table>';
    }

    /** @param array{recettes: int, depenses_total: int, benefice: int, deficitaire: bool} $bilan */
    private function conclusion(BusVehicule $vehicule, array $bilan): string
    {
        $texte = $bilan['deficitaire']
            ? "Sur la période considérée, le véhicule {$vehicule->immatriculation} coûte plus qu'il ne rapporte : il fait fonctionner le complexe à perte, d'un montant de ".$this->francs(abs($bilan['benefice'])).' F.'
            : "Sur la période considérée, le véhicule {$vehicule->immatriculation} rapporte plus qu'il ne coûte : il fait fonctionner le complexe à profit, à hauteur de ".$this->francs($bilan['benefice']).' F.';

        return '<div class="conclusion"><b>Conclusion :</b> '.$this->e($texte).'</div>';
    }

    private function signature(BusVehicule $vehicule): string
    {
        $ville = trim(explode(',', (string) $vehicule->school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;font-size:2.8mm;vertical-align:top;">'
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

    private function jour(string $date): string
    {
        return implode('/', array_reverse(explode('-', $date)));
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
