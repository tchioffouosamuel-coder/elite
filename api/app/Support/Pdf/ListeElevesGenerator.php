<?php

namespace App\Support\Pdf;

use App\Models\Classe;
use App\Models\School;
use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Database\Eloquent\Collection;
use Mpdf\Output\Destination;

/** Liste nominative des élèves d'une classe, à l'image du fichier du personnel. */
class ListeElevesGenerator
{
    use RenduDocument;

    public function build(Classe $classe, Collection $eleves): string
    {
        return $this->rendre(
            $classe->school,
            'Classe <i>/ Class</i> : '.$this->e($classe->nom)
                .' &nbsp;|&nbsp; Année scolaire <i>/ Academic year</i> : '.$this->e($classe->anneeScolaire?->libelle ?? '—'),
            $eleves,
            'Liste des élèves — '.$classe->nom,
        );
    }

    /** Toute l'école, toutes classes confondues — la même mise en page, sans le filtre d'une classe. */
    public function buildEcole(School $school, Collection $eleves): string
    {
        return $this->rendre(
            $school,
            'Établissement <i>/ School</i> : '.$this->e($school->name),
            $eleves,
            'Liste des élèves — '.$school->name,
        );
    }

    private function rendre(School $school, string $bandeauLibelle, Collection $eleves, string $titrePage): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle($titrePage);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<hr>'
                .$this->titre($bandeauLibelle, $eleves)
                .$this->tableau($eleves)
                .$this->signature($school)
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

    private function titre(string $bandeauLibelle, Collection $eleves): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Liste des élèves</span><br>'
            .'<span class="titre-en">Student list</span>'
            .'</div>'
            .'<div class="bandeau">'
            .$bandeauLibelle
            .' &nbsp;|&nbsp; Effectif <i>/ Headcount</i> : '.$eleves->count()
            .'</div>';
    }

    private function tableau(Collection $eleves): string
    {
        $lignes = '';
        $rang = 1;

        foreach ($eleves as $eleve) {
            $tuteur = $eleve->tuteurs->first();

            $lignes .= '<tr>'
                .'<td>'.$rang.'</td>'
                .'<td>'.$this->e($eleve->matricule ?: '—').'</td>'
                .'<td class="left nom">'.$this->e($eleve->nom_complet).'</td>'
                .'<td>'.($eleve->sexe === 'F' ? 'F' : 'M').'</td>'
                .'<td>'.($eleve->age ?? '—').'</td>'
                .'<td class="left">'.$this->e($tuteur?->nom_complet ?: '—').'</td>'
                .'<td>'.$this->e($tuteur?->telephone ?: '—').'</td>'
                .'</tr>';
            $rang++;
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="7" style="padding:6mm;">Aucun élève dans cette classe.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'
            .'<th style="width:5%;">N°</th>'
            .'<th style="width:13%;">Matricule<br><i>ID number</i></th>'
            .'<th style="width:25%;">Nom et prénoms<br><i>Full name</i></th>'
            .'<th style="width:7%;">Sexe<br><i>Sex</i></th>'
            .'<th style="width:7%;">Âge<br><i>Age</i></th>'
            .'<th style="width:25%;">Tuteur / Parent<br><i>Guardian</i></th>'
            .'<th style="width:18%;">Téléphone<br><i>Phone</i></th>'
            .'</tr></thead><tbody>'.$lignes.'</tbody></table>';
    }

    private function signature(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $lieu = $ville !== '' ? 'Fait à '.$ville.', le ' : 'Fait le ';

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.8mm;">'
            .$this->e($lieu).date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i>'
            .$this->visa($school)
            .'<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature et cachet</span>'
            .'</td></tr></table>';
    }

    /** Cachet et signature composés en une image (la signature traverse le cachet), si chargés. */
    private function visa(School $school): string
    {
        $visa = (new VisaComposeService)->chemin($school);

        return $visa !== null
            ? '<img src="'.$this->e($visa).'" style="height:46px;">'
            : '<br><br><br><br>';
    }
}
