<?php

namespace App\Support\Pdf;

use App\Models\Personnel;
use App\Models\School;
use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Carbon;
use Mpdf\Output\Destination;

/**
 * Fiche d'identification du personnel : le dossier administratif d'un agent
 * (état civil, coordonnées, poste, diplômes, filiation) sur un seul document,
 * portrait au format A4 — pendant du certificat de scolarité côté élèves,
 * mais pour le personnel et sans registre de numérotation : ce n'est pas une
 * pièce délivrée à un tiers, seulement le dossier tel qu'il est enregistré.
 */
class FicheIdentitePersonnelGenerator
{
    use RenduDocument;

    public function build(Personnel $personnel): string
    {
        $school = $personnel->school;

        $mpdf = MpdfFactory::make([
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 10,
        ], $school);
        $mpdf->SetTitle('Fiche d\'identification — '.$personnel->nom_complet);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<hr>'
                .$this->titre($personnel)
                .$this->identite($personnel)
                .$this->emploi($personnel)
                .$this->diplomesEtBanque($personnel)
                .$this->filiation($personnel)
                .$this->enfants($personnel)
                .$this->signature($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.section{margin-top:4mm}'
            .'.section h4{margin:0 0 1.5mm 0;padding-bottom:0.8mm;font-size:3mm;'
            .'color:'.self::ARDOISE.';text-transform:uppercase;letter-spacing:0.2mm;'
            .'text-align:left;border-bottom:0.5mm solid '.self::ACCENT.'}'
            .'.grille td{border:none;text-align:left;vertical-align:top;padding:1mm 2mm 1mm 0;width:50%}'
            .'.libelle{font-size:2.3mm;color:#777;text-transform:uppercase}'
            .'.valeur{font-size:2.9mm;font-weight:bold;color:#000}'
            .'.photo-cell{width:28mm;text-align:right;vertical-align:top}'
            .'.photo{width:26mm;height:32mm;object-fit:cover;border:0.5px solid #bdc3c7}';
    }

    private function titre(Personnel $personnel): string
    {
        $photo = $this->cheminImage($personnel->photo_path);

        return '<table class="no-border" style="margin-bottom:2mm;"><tr>'
            .'<td class="no-border left" style="vertical-align:top;">'
            .'<span class="titre">Fiche d\'identification du personnel</span><br>'
            .'<span class="titre-en">Staff identification sheet</span>'
            .'</td>'
            .($photo !== null
                ? '<td class="no-border photo-cell"><img src="'.$this->e($photo).'" class="photo"></td>'
                : '')
            .'</tr></table>';
    }

    /** Une ligne « Libellé / Label » au-dessus de la valeur, deux par ligne de tableau. */
    private function champ(string $fr, string $en, ?string $valeur): string
    {
        return '<td><div class="libelle">'.$this->e($fr).' / '.$this->e($en).'</div>'
            .'<div class="valeur">'.($valeur !== null && trim($valeur) !== '' ? $this->e($valeur) : '—').'</div></td>';
    }

    /** @param list<array{0: string, 1: string, 2: ?string}> $champs */
    private function grille(array $champs): string
    {
        $lignes = '';
        foreach (array_chunk($champs, 2) as $paire) {
            $lignes .= '<tr>';
            foreach ($paire as [$fr, $en, $valeur]) {
                $lignes .= $this->champ($fr, $en, $valeur);
            }
            if (count($paire) === 1) {
                $lignes .= '<td></td>';
            }
            $lignes .= '</tr>';
        }

        return '<table class="no-border grille">'.$lignes.'</table>';
    }

    private function identite(Personnel $personnel): string
    {
        $feminin = $personnel->sexe === 'F';

        return '<div class="section"><h4>Identité <i>/ Identity</i></h4>'
            .$this->grille([
                ['Matricule', 'Staff ID', $personnel->matricule],
                ['Civilité', 'Title', $personnel->civilite],
                ['Nom et prénoms', 'Full name', mb_strtoupper($personnel->nom_complet)],
                ['Sexe', 'Sex', $personnel->sexe ? ($feminin ? 'Féminin' : 'Masculin') : null],
                [$feminin ? 'Née le' : 'Né le', 'Born on', $personnel->date_naissance?->format('d/m/Y')],
                ['Situation matrimoniale', 'Marital status', $this->situationMatrimoniale($personnel->situation_matrimoniale)],
                ['N° CNI', 'ID card N°', $personnel->numero_cni],
                ['N° CNPS', 'Social security N°', $personnel->numero_cnps],
                ['Département d\'origine', 'Home department', $personnel->departement_origine],
                ['Résidence', 'Residence', $personnel->residence],
                ['Téléphone', 'Phone', $personnel->telephone],
                ['Téléphone secondaire', 'Secondary phone', $personnel->telephone_2],
                ['E-mail', 'Email', $personnel->email],
            ])
            .'</div>';
    }

    private function emploi(Personnel $personnel): string
    {
        return '<div class="section"><h4>Emploi <i>/ Employment</i></h4>'
            .$this->grille([
                ['Fonction', 'Position', $personnel->fonction],
                ['Département / Service', 'Department', $personnel->departement?->nom],
                ['Affectation', 'Duty post', $personnel->affectation],
                ['En poste depuis le', 'Employed since', $personnel->date_embauche?->format('d/m/Y')],
                ['Ancienneté', 'Longevity', $this->anciennete($personnel)],
                ['N° permis de conduire', 'Driving licence N°', $personnel->numero_permis],
                ['Statut', 'Status', $personnel->statut === 'actif' ? 'Actif' : 'Ex-employé'],
                ['Départ prévu à la retraite', 'Expected retirement', $this->dateFormatee($personnel->date_retraite_calculee)],
            ])
            .'</div>';
    }

    private function diplomesEtBanque(Personnel $personnel): string
    {
        return '<div class="section"><h4>Diplômes &amp; coordonnées bancaires <i>/ Qualifications &amp; bank details</i></h4>'
            .$this->grille([
                ['Diplôme académique', 'Academic qualification', $personnel->diplome_academique],
                ['Diplôme professionnel', 'Professional qualification', $personnel->diplome_professionnel],
                ['Banque', 'Bank', $personnel->banque],
                ['N° de compte', 'Account N°', $personnel->numero_compte],
            ])
            .'</div>';
    }

    private function filiation(Personnel $personnel): string
    {
        return '<div class="section"><h4>Filiation <i>/ Parentage</i></h4>'
            .$this->grille([
                ['Père', 'Father', $personnel->pere_nom_complet],
                ['Statut', 'Status', $this->statutParent($personnel->pere_statut)],
                ['Téléphone du père', 'Father\'s phone', $personnel->pere_telephone],
                ['Mère', 'Mother', $personnel->mere_nom_complet],
                ['Statut', 'Status', $this->statutParent($personnel->mere_statut)],
                ['Téléphone de la mère', 'Mother\'s phone', $personnel->mere_telephone],
                ['Nombre d\'enfants', 'Number of children', $personnel->nombre_enfants !== null ? (string) $personnel->nombre_enfants : null],
            ])
            .'</div>';
    }

    private function enfants(Personnel $personnel): string
    {
        $enfants = $personnel->enfants ?? [];

        if ($enfants === []) {
            return '';
        }

        $lignes = '';
        foreach ($enfants as $enfant) {
            $lignes .= '<tr>'
                .'<td class="left">'.$this->e($enfant['nom_complet'] ?? '—').'</td>'
                .'<td>'.$this->e(($enfant['sexe'] ?? null) === 'F' ? 'F' : 'M').'</td>'
                .'<td>'.$this->e($this->dateFormatee($enfant['date_naissance'] ?? null)).'</td>'
                .'</tr>';
        }

        return '<div class="section"><h4>Enfants <i>/ Children</i></h4>'
            .'<table><thead><tr>'
            .'<th style="width:60%;">Nom et prénoms<br><i>Full name</i></th>'
            .'<th style="width:15%;">Sexe<br><i>Sex</i></th>'
            .'<th style="width:25%;">Date de naissance<br><i>Date of birth</i></th>'
            .'</tr></thead><tbody>'.$lignes.'</tbody></table>'
            .'</div>';
    }

    private function signature(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            .'<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.8mm;">'
            .'Fait à '.$this->e($ville !== '' ? $ville : '…………').', le '.date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i>'
            .$this->visa($school)
            .'<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature et cachet</span>'
            .'</td></tr></table>';
    }

    private function visa(School $school): string
    {
        $visa = (new VisaComposeService)->chemin($school);

        return $visa !== null
            ? '<img src="'.$this->e($visa).'" style="height:46px;">'
            : '<br><br><br><br>';
    }

    private function situationMatrimoniale(?string $valeur): ?string
    {
        return match ($valeur) {
            'celibataire' => 'Célibataire',
            'marie' => 'Marié(e)',
            'divorce' => 'Divorcé(e)',
            'veuf' => 'Veuf/Veuve',
            default => null,
        };
    }

    private function statutParent(?string $valeur): ?string
    {
        return match ($valeur) {
            'vivant' => 'Vivant',
            'decede' => 'Décédé',
            default => null,
        };
    }

    private function dateFormatee(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    /** Ancienneté en années et mois, arrêtée au jour de l'émission. */
    private function anciennete(Personnel $personnel): ?string
    {
        if (! $personnel->date_embauche) {
            return null;
        }

        $ecart = $personnel->date_embauche->diff(Carbon::today());
        $annees = $ecart->y;
        $mois = $ecart->m;

        return trim(
            ($annees > 0 ? $annees.' an'.($annees > 1 ? 's' : '').' ' : '')
                .($mois > 0 || $annees === 0 ? $mois.' mois' : '')
        );
    }
}
