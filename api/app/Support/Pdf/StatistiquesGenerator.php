<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Statistiques pédagogiques et disciplinaires de l'établissement.
 *
 * _smapp produisait une archive ZIP contenant un PDF par classe
 * (`generate_stats_batch_advanced.php`). On rend ici un document unique : une
 * page de synthèse consolidée, puis une page par classe. Un seul fichier à
 * ouvrir, imprimer et archiver plutôt qu'une archive à décompresser.
 */
class StatistiquesGenerator
{
    use RenduDocument;

    private const CATEGORIES = [
        'redoublants' => 'Redoublants',
        'nouveaux' => 'Nouveaux',
        'garcons' => 'Garçons',
        'filles' => 'Filles',
        'total' => 'Total',
    ];

    private const BANDES = [
        'cvwa' => '≥ 16',
        'cwa' => '14 – 16',
        'ca' => '12 – 14',
        'caa' => '10 – 12',
        'cna' => '< 10',
    ];

    /** Catalogue de la table `sanctions`, dans l'ordre de gravité croissante. */
    private const TYPES_SANCTION = [
        'corvee' => 'Corvée',
        'exclusion_temporaire' => 'Exclusion temporaire',
        'exclusion_definitive' => 'Exclusion définitive',
        'autre' => 'Autre',
    ];

    public function pedagogiques(array $donnees, School $school): string
    {
        $pages = [$this->pageSynthesePedagogique($donnees, $school)];

        foreach ($donnees['classes'] as $classe) {
            $pages[] = $this->pageClassePedagogique($classe, $donnees, $school);
        }

        return $this->rendre($pages);
    }

    public function disciplinaires(array $donnees, School $school): string
    {
        $pages = [$this->pageSyntheseDisciplinaire($donnees, $school)];

        foreach ($donnees['classes'] as $classe) {
            $pages[] = $this->pageClasseDisciplinaire($classe, $donnees, $school);
        }

        return $this->rendre($pages);
    }

    private function rendre(array $pages): string
    {
        $mpdf = MpdfFactory::make(['format' => 'A4']);
        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
            .implode('<pagebreak />', $pages)
            .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.sous-titre{font-weight:bold;color:'.self::ARDOISE.';font-size:3.4mm;margin-top:6px}'
            .'.cat{text-align:left!important;font-weight:bold}'
            .'.tot{background-color:#f3ede0;font-weight:bold}'
            .'.jauge{margin:0;border:none}'
            .'.jauge td{border:none;font-size:1mm;line-height:2.6mm;padding:0}';
    }

    private function titre(School $school, string $fr, string $en, string $trimestre): string
    {
        return $this->enTeteEcole($school)
            .'<table class="no-border"><tr><td class="left" style="line-height:1.4;">'
            .'<span class="titre">'.$this->e($fr).'</span><br>'
            .'<span class="titre-en">'.$this->e($en).'</span><br>'
            .'<span style="font-size:2.8mm;">Période <i>/ Term</i> : <b>'.$this->e($trimestre).'</b></span>'
            .'</td></tr></table>';
    }

    /** Tableau des catégories : une ligne par catégorie, colonnes = indicateurs. */
    private function tableauCategories(array $categories): string
    {
        $html = '<table><tr>'
            .'<th class="left" style="width:22%;">Catégorie</th>'
            .'<th style="width:11%;">Effectif</th><th style="width:11%;">Notés</th><th style="width:11%;">Admis</th>'
            .'<th style="width:16%;">Taux de réussite</th><th style="width:15%;">Participation</th>'
            .'<th style="width:14%;">Tableau d\'honneur</th>'
            .'</tr>';

        foreach (self::CATEGORIES as $cle => $libelle) {
            $c = $categories[$cle];
            $classe = $cle === 'total' ? ' class="tot"' : '';

            $html .= '<tr'.$classe.'>'
                .'<td class="cat">'.$this->e($libelle).'</td>'
                .'<td>'.$c['effectif'].'</td>'
                .'<td>'.$c['notes'].'</td>'
                .'<td>'.$c['admis'].'</td>'
                .'<td>'.$this->nombre($c['taux_reussite'], 2).' %</td>'
                .'<td>'.$this->nombre($c['taux_participation'], 2).' %</td>'
                .'<td>'.$c['honneur'].'</td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    /** Répartition par bande de moyenne, reprise des cotes CVWA→CNA de _smapp. */
    private function tableauBandes(array $categories): string
    {
        $html = '<table><tr><th class="left">Catégorie</th>';
        foreach (self::BANDES as $libelle) {
            $html .= '<th>'.$this->e($libelle).'</th>';
        }
        $html .= '</tr>';

        foreach (self::CATEGORIES as $cle => $libelle) {
            $c = $categories[$cle];
            $classe = $cle === 'total' ? ' class="tot"' : '';

            $html .= '<tr'.$classe.'><td class="cat">'.$this->e($libelle).'</td>';
            foreach (array_keys(self::BANDES) as $bande) {
                $html .= '<td>'.$c[$bande].'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    /**
     * Comparatif des classes. La jauge remplace le graphique Chart.js de _smapp,
     * qui dépendait d'un service HTTP externe pour produire son image — une
     * barre en CSS suffit et n'ajoute aucune dépendance réseau à la génération.
     */
    private function tableauComparatif(array $classes): string
    {
        $html = '<table><tr>'
            .'<th class="left" style="width:16%;">Classe</th><th style="width:11%;">Effectif</th>'
            .'<th style="width:11%;">Admis</th><th style="width:16%;">Taux de réussite</th>'
            .'<th style="width:30%;">Comparatif</th><th style="width:16%;">Moyenne de classe</th>'
            .'</tr>';

        foreach ($classes as $classe) {
            $total = $classe['categories']['total'];
            $taux = $total['taux_reussite'];

            $html .= '<tr>'
                .'<td class="cat">'.$this->e($classe['classe']['nom']).'</td>'
                .'<td>'.$total['effectif'].'</td>'
                .'<td>'.$total['admis'].'</td>'
                .'<td>'.$this->nombre($taux, 2).' %</td>'
                .'<td>'.$this->jauge($taux).'</td>'
                .'<td>'.$this->nombre($classe['moyenne_classe'], 2).'</td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    /**
     * Barre de progression. mPDF ignore les div imbriqués à largeur relative :
     * un tableau à deux cellules colorées est le seul rendu fiable.
     */
    private function jauge(float $pourcentage): string
    {
        $rempli = max(0.0, min(100.0, $pourcentage));
        $vide = 100.0 - $rempli;

        $cellules = $rempli > 0
            ? '<td style="background-color:'.self::OR.';width:'.$rempli.'%;">&nbsp;</td>'
            : '';

        // Une cellule de largeur nulle décale la mise en page : on l'omet.
        if ($vide > 0) {
            $cellules .= '<td style="background-color:#e6e6e6;width:'.$vide.'%;">&nbsp;</td>';
        }

        return '<table class="jauge"><tr>'.$cellules.'</tr></table>';
    }

    private function pageSynthesePedagogique(array $donnees, School $school): string
    {
        return $this->titre($school, 'Statistiques pédagogiques', 'Academic statistics', $donnees['trimestre']['libelle'])
            .'<div class="sous-titre">Consolidé de l\'établissement</div>'
            .$this->tableauCategories($donnees['consolide'])
            .'<div class="sous-titre">Répartition par tranche de moyenne</div>'
            .$this->tableauBandes($donnees['consolide'])
            .'<div class="sous-titre">Comparatif des classes</div>'
            .$this->tableauComparatif($donnees['classes'])
            .'<span class="legende">Les pourcentages sont rapportés à l\'effectif de la catégorie, '
            .'non au nombre d\'élèves effectivement notés.</span>';
    }

    private function pageClassePedagogique(array $classe, array $donnees, School $school): string
    {
        return $this->titre(
            $school,
            'Statistiques pédagogiques — '.$classe['classe']['nom'],
            'Academic statistics',
            $donnees['trimestre']['libelle']
        )
            .$this->tableauCategories($classe['categories'])
            .'<div class="sous-titre">Répartition par tranche de moyenne</div>'
            .$this->tableauBandes($classe['categories'])
            .'<table><tr>'
            .'<th>Moyenne de classe</th><th>Plus forte moyenne</th><th>Plus faible moyenne</th><th>Seuil tableau d\'honneur</th>'
            .'</tr><tr>'
            .'<td class="value">'.$this->nombre($classe['moyenne_classe'], 2).'</td>'
            .'<td>'.$this->nombre($classe['moyenne_plus_forte'], 2).'</td>'
            .'<td>'.$this->nombre($classe['moyenne_plus_faible'], 2).'</td>'
            .'<td>'.$this->nombre($classe['seuil_honneur'], 2).'</td>'
            .'</tr></table>';
    }

    private function tableauSanctions(array $parType, int $total): string
    {
        $html = '<table><tr><th class="left">Type de sanction</th><th>Nombre</th><th>Part</th></tr>';

        foreach (self::TYPES_SANCTION as $cle => $libelle) {
            $nombre = $parType[$cle] ?? 0;
            $part = $total > 0 ? $nombre / $total * 100 : 0;

            $html .= '<tr>'
                .'<td class="cat">'.$this->e($libelle).'</td>'
                .'<td>'.$nombre.'</td>'
                .'<td>'.$this->nombre($part, 1).' %</td>'
                .'</tr>';
        }

        return $html.'<tr class="tot"><td class="cat">Total</td><td>'.$total.'</td><td>—</td></tr></table>';
    }

    private function pageSyntheseDisciplinaire(array $donnees, School $school): string
    {
        $c = $donnees['consolide'];
        $moyenne = $c['effectif'] > 0 ? $c['heures_non_justifiees'] / $c['effectif'] : 0;

        $html = $this->titre($school, 'Statistiques disciplinaires', 'Disciplinary statistics', $donnees['trimestre']['libelle'])
            .'<div class="sous-titre">Consolidé de l\'établissement</div>'
            .'<table><tr>'
            .'<th>Effectif</th><th>Élèves sanctionnés</th><th>Total sanctions</th>'
            .'<th>Heures non justifiées</th><th>Moyenne / élève</th>'
            .'</tr><tr>'
            .'<td>'.$c['effectif'].'</td>'
            .'<td>'.$c['eleves_sanctionnes'].'</td>'
            .'<td class="value">'.$c['total_sanctions'].'</td>'
            .'<td>'.$this->nombre($c['heures_non_justifiees'], 1).' h</td>'
            .'<td>'.$this->nombre($moyenne, 1).' h</td>'
            .'</tr></table>'
            .'<div class="sous-titre">Répartition des sanctions</div>'
            .$this->tableauSanctions($c['sanctions_par_type'], $c['total_sanctions'])
            .'<div class="sous-titre">Comparatif des classes</div>'
            .'<table><tr>'
            .'<th class="left">Classe</th><th>Effectif</th><th>Sanctions</th><th>Élèves sanctionnés</th>'
            .'<th>Heures non justifiées</th><th>Moyenne / élève</th>'
            .'</tr>';

        foreach ($donnees['classes'] as $classe) {
            $bilan = $classe['bilan'];
            $html .= '<tr>'
                .'<td class="cat">'.$this->e($classe['classe']['nom']).'</td>'
                .'<td>'.$bilan['effectif'].'</td>'
                .'<td>'.$classe['total_sanctions'].'</td>'
                .'<td>'.$classe['eleves_sanctionnes'].'</td>'
                .'<td>'.$this->nombre($bilan['total_hnj'], 1).' h</td>'
                .'<td>'.$this->nombre($bilan['moyenne_hnj'], 1).' h</td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    private function pageClasseDisciplinaire(array $classe, array $donnees, School $school): string
    {
        $bilan = $classe['bilan'];

        $plusAbsent = $bilan['eleve_plus_absent']
            ? $this->e($bilan['eleve_plus_absent']['nom_complet'])
                .' ('.$this->nombre($bilan['eleve_plus_absent']['heures_non_justifiees'], 1).' h)'
            : '—';

        return $this->titre(
            $school,
            'Statistiques disciplinaires — '.$classe['classe']['nom'],
            'Disciplinary statistics',
            $donnees['trimestre']['libelle']
        )
            .'<table><tr>'
            .'<th>Effectif</th><th>Élèves sanctionnés</th><th>Total sanctions</th><th>Heures non justifiées</th><th>Moyenne / élève</th>'
            .'</tr><tr>'
            .'<td>'.$bilan['effectif'].'</td>'
            .'<td>'.$classe['eleves_sanctionnes'].'</td>'
            .'<td class="value">'.$classe['total_sanctions'].'</td>'
            .'<td>'.$this->nombre($bilan['total_hnj'], 1).' h</td>'
            .'<td>'.$this->nombre($bilan['moyenne_hnj'], 1).' h</td>'
            .'</tr></table>'
            .'<div class="sous-titre">Absences par genre</div>'
            .'<table><tr><th class="left">Genre</th><th>Total heures non justifiées</th><th>Moyenne / élève</th></tr>'
            .'<tr><td class="cat">Garçons</td><td>'.$this->nombre($bilan['total_hnj_garcons'], 1).' h</td>'
            .'<td>'.$this->nombre($bilan['moyenne_hnj_garcons'], 1).' h</td></tr>'
            .'<tr><td class="cat">Filles</td><td>'.$this->nombre($bilan['total_hnj_filles'], 1).' h</td>'
            .'<td>'.$this->nombre($bilan['moyenne_hnj_filles'], 1).' h</td></tr>'
            .'<tr class="tot"><td class="cat">Total</td><td>'.$this->nombre($bilan['total_hnj'], 1).' h</td>'
            .'<td>'.$this->nombre($bilan['moyenne_hnj'], 1).' h</td></tr></table>'
            .'<div class="sous-titre">Répartition des sanctions</div>'
            .$this->tableauSanctions($classe['sanctions_par_type'], $classe['total_sanctions'])
            .'<table class="no-border"><tr><td class="left">'
            .'<b>Élève le plus absent :</b> '.$plusAbsent
            .'</td></tr></table>';
    }
}
