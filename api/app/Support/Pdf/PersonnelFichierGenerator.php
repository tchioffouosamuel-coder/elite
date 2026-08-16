<?php

namespace App\Support\Pdf;

use App\Models\AnneeScolaire;
use App\Models\Personnel;
use App\Models\School;
use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Fichier du personnel, portage de `fichier_du_personnel.php` (_smapp) : le
 * registre nominatif des agents en poste, destiné à être signé et archivé.
 *
 * Deux écarts avec le legacy, tous deux dictés par notre schéma :
 *
 * - _smapp affiche un « grade » (colonne `user_grade`) que nous n'avons pas ;
 *   le département tient ce rôle de rattachement dans la grille comme dans la
 *   ventilation. Au primaire et en maternelle, qui s'organisent par niveaux,
 *   la colonne reste vide — c'est fidèle à la réalité de ces écoles.
 * - _smapp appelle une API externe (api.qrserver.com) pour incruster un QR
 *   d'authentification. Générer un document ne doit pas dépendre d'un service
 *   tiers joignable : la vérification par QR signé reste prévue en phase 2,
 *   pour l'ensemble des documents.
 */
class PersonnelFichierGenerator
{
    use RenduDocument;

    public function build(array $donnees, School $school, ?AnneeScolaire $annee): string
    {
        // Paysage : sept colonnes dont deux champs longs (fonction, email).
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle('Fichier du personnel — '.$school->name);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<hr>'
                .$this->titre($annee, $donnees)
                .$this->tableau($donnees, $school)
                .$this->ventilations($donnees, $school)
                .$this->signature($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0}'
            .'.registre th{font-size:2.5mm}'
            .'.registre td{font-size:2.6mm;padding:1.2mm 1mm}'
            .'.registre tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.nom{font-weight:bold;text-transform:uppercase}'
            .'.etiquette{background:#f1ecdd;border:0.4px solid '.self::ACCENT.';border-radius:2mm;'
            .'padding:0.4mm 1.4mm;font-size:2.4mm}'
            .'.total td{background:'.self::ARDOISE.';color:#fff;font-weight:bold;font-size:2.8mm}'
            // Les cellules du document sont centrées par défaut : une liste de
            // ventilation se lit alignée à gauche.
            .'.ventilation{text-align:left}'
            .'.ventilation h4{margin:0 0 1.5mm 0;padding-bottom:0.8mm;font-size:2.9mm;'
            .'color:'.self::ARDOISE.';text-transform:uppercase;letter-spacing:0.2mm;'
            .'text-align:left;border-bottom:0.4mm solid '.self::ACCENT.'}'
            .'.ventilation .ligne{font-size:2.5mm;margin:0.8mm 0;text-align:left}'
            .'.ventilation .part{color:#888}';
    }

    private function titre(?AnneeScolaire $annee, array $donnees): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">Fichier du personnel</span><br>'
            .'<span class="titre-en">Staff file</span>'
            .'</div>'
            .'<div class="bandeau">'
            .'Année scolaire <i>/ Academic year</i> : '.$this->e($annee?->libelle ?? '—')
            .' &nbsp;|&nbsp; Effectif <i>/ Headcount</i> : '.$donnees['total']
            .'</div>';
    }

    private function tableau(array $donnees, School $school): string
    {
        $isSecondaire = $school->estSecondaire();
        $lignes = '';
        $rang = 1;

        foreach ($donnees['personnels'] as $personnel) {
            $lignes .= '<tr>'
                .'<td>'.$rang.'</td>'
                .'<td class="left nom">'.$this->e($personnel->nom_complet).'</td>'
                .'<td>'.$this->e($personnel->matricule ?: '—').'</td>'
                .'<td class="left">'.$this->etiquette($personnel->fonction).'</td>';

            if ($isSecondaire) {
                $lignes .= '<td class="left">'.$this->etiquette($personnel->departement?->nom).'</td>';
            }

            $lignes .= '<td>'.$this->e($this->telephone($personnel)).'</td>';

            if (! $isSecondaire) {
                // Afficher les classes tenues pour le primaire/maternelle
                $classesTenues = $personnel->classesTenues
                    ->map(fn ($classe) => $classe->nom)
                    ->join(', ');
                $lignes .= '<td class="left">'.$this->e($classesTenues ?: '—').'</td>';
            }

            $lignes .= '</tr>';
            $rang++;
        }

        // 5 colonnes communes ; le secondaire ajoute le département, les autres
        // cycles la classe tenue par le titulaire.
        $colspanFooter = 6;
        if ($lignes === '') {
            $lignes = '<tr><td colspan="'.$colspanFooter.'" style="padding:6mm;">Aucun personnel en poste.</td></tr>';
        }

        $headerHtml = '<th style="width:5%;">N°</th>'
            .'<th style="width:22%;">Nom et prénoms<br><i>Full name</i></th>'
            .'<th style="width:12%;">Matricule<br><i>ID number</i></th>'
            .'<th style="width:19%;">Fonction<br><i>Position</i></th>';

        if ($isSecondaire) {
            $headerHtml .= '<th style="width:26%;">Département<br><i>Department</i></th>';
        }

        $headerHtml .= '<th style="width:16%;">Téléphone<br><i>Phone</i></th>';

        if (! $isSecondaire) {
            $headerHtml .= '<th style="width:18%;">Classe tenue<br><i>Assigned class</i></th>';
        }

        return '<table class="registre"><thead><tr>'
            .$headerHtml
            .'</tr></thead><tbody>'.$lignes.'</tbody>'
            .'<tfoot><tr class="total"><td colspan="'.$colspanFooter.'">'
            .'Total : '.$donnees['total'].' membre(s) du personnel en poste'
            .' &nbsp;|&nbsp; '.$donnees['avec_acces'].' disposant d\'un accès à la plateforme'
            .'</td></tr></tfoot></table>';
    }

    /** Les deux ventilations côte à côte, comme les statistiques de _smapp. */
    private function ventilations(array $donnees, School $school): string
    {
        // Hors secondaire, la ventilation par département n'aurait qu'une ligne
        // « Non rattaché » : la répartition par fonction occupe alors la largeur.
        if (! $school->estSecondaire()) {
            return '<div style="margin-top:4mm;">'
                .$this->ventilation('Répartition par fonction', $donnees['par_fonction'], $donnees['total'])
                .'</div>';
        }

        return '<table class="no-border" style="margin-top:4mm;"><tr>'
            .'<td class="no-border left" style="width:50%;padding-right:2mm;vertical-align:top;">'
            .$this->ventilation('Répartition par fonction', $donnees['par_fonction'], $donnees['total'])
            .'</td>'
            .'<td class="no-border left" style="width:50%;padding-left:2mm;vertical-align:top;">'
            .$this->ventilation('Répartition par département', $donnees['par_departement'], $donnees['total'])
            .'</td>'
            .'</tr></table>';
    }

    /** @param array<string, int> $comptes */
    private function ventilation(string $titre, array $comptes, int $total): string
    {
        $lignes = '';

        foreach ($comptes as $libelle => $nombre) {
            $part = $total > 0 ? round($nombre / $total * 100, 1) : 0.0;
            $lignes .= '<div class="ligne">'
                .$this->e((string) $libelle).' : <b>'.$nombre.'</b> '
                .'<span class="part">('.number_format($part, 1, ',', ' ').' %)</span>'
                .'</div>';
        }

        return '<div class="ventilation"><h4>'.$this->e($titre).'</h4>'
            .($lignes ?: '<div class="ligne">—</div>')
            .'</div>';
    }

    private function signature(School $school): string
    {
        // « Fait à … » attend une ville : on prend le premier segment de
        // l'adresse, le reste (pays, quartier) alourdirait la mention.
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

    private function etiquette(?string $valeur): string
    {
        return $valeur
            ? '<span class="etiquette">'.$this->e($valeur).'</span>'
            : '—';
    }

    /** L'indicatif pays alourdit une colonne déjà étroite. */
    private function telephone(Personnel $personnel): string
    {
        return $personnel->telephone
            ? trim(str_replace('+237', '', $personnel->telephone))
            : '—';
    }
}
