<?php

namespace App\Support\Pdf;

use App\Models\BusAffectation;
use App\Models\BusVehicule;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/** Liste nominative des élèves embarqués sur un véhicule, tous trajets confondus. */
class ListeElevesBusGenerator
{
    use RenduDocument;

    private const LIBELLES_OPTION = [
        'aller_simple' => 'Aller',
        'retour_simple' => 'Retour',
        'aller_retour' => 'Aller & retour',
    ];

    /** @param  Collection<int, BusAffectation>  $affectations */
    public function build(BusVehicule $vehicule, Collection $affectations): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $vehicule->school);
        $mpdf->SetTitle('Élèves du véhicule '.$vehicule->immatriculation);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($vehicule->school)
                .'<hr>'
                .$this->titre($vehicule, $affectations)
                .$this->tableau($affectations)
                .$this->signature($vehicule)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0}'
            .'.liste th{font-size:2.6mm}'
            .'.liste td{font-size:2.7mm;padding:1.2mm 1mm}'
            .'.liste tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.nom{font-weight:bold;text-transform:uppercase}';
    }

    /** @param  Collection<int, BusAffectation>  $affectations */
    private function titre(BusVehicule $vehicule, Collection $affectations): string
    {
        $identite = $vehicule->immatriculation.($vehicule->couleur ? ' — '.$vehicule->couleur : '');

        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Liste des élèves du bus</span><br>'
            .'<span class="titre-en">Bus student list</span>'
            .'</div>'
            .'<div class="bandeau">'
            .'Véhicule <i>/ Vehicle</i> : '.$this->e($identite)
            .' &nbsp;|&nbsp; Effectif <i>/ Headcount</i> : '.$affectations->count()
            .'</div>';
    }

    /** @param  Collection<int, BusAffectation>  $affectations */
    private function tableau(Collection $affectations): string
    {
        $lignes = '';
        $rang = 1;

        foreach ($affectations->sortBy(fn (BusAffectation $a) => $a->eleve?->nom_complet) as $affectation) {
            $eleve = $affectation->eleve;

            $lignes .= '<tr>'
                .'<td>'.$rang.'</td>'
                .'<td class="left nom">'.$this->e($eleve?->nom_complet ?: '—').'</td>'
                .'<td class="left">'.$this->e($eleve?->classe?->nom ?: '—').'</td>'
                .'<td class="left">'.$this->e($affectation->trajet?->nom ?: '—').'</td>'
                .'<td class="left">'.$this->e($affectation->arret?->nom ?: '—').'</td>'
                .'<td>'.$this->e(self::LIBELLES_OPTION[$affectation->option_trajet] ?? $affectation->option_trajet).'</td>'
                .'</tr>';
            $rang++;
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="6" style="padding:6mm;">Aucun élève affecté à ce véhicule.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'
            .'<th style="width:6%;">N°</th>'
            .'<th style="width:26%;">Nom et prénoms<br><i>Full name</i></th>'
            .'<th style="width:16%;">Classe<br><i>Class</i></th>'
            .'<th style="width:20%;">Trajet<br><i>Route</i></th>'
            .'<th style="width:16%;">Arrêt<br><i>Stop</i></th>'
            .'<th style="width:16%;">Souscription<br><i>Subscription</i></th>'
            .'</tr></thead><tbody>'.$lignes.'</tbody></table>';
    }

    private function signature(BusVehicule $vehicule): string
    {
        $ville = trim(explode(',', (string) $vehicule->school->address)[0] ?? '');
        $lieu = $ville !== '' ? 'Fait à '.$ville.', le ' : 'Fait le ';

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.8mm;">'
            .$this->e($lieu).date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            .'<b>Responsable du transport scolaire</b><br><i>Transport officer</i>'
            .'<br><br><br><br>'
            .'<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature</span>'
            .'</td></tr></table>';
    }
}
