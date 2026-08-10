<?php

namespace App\Support\Pdf;

use App\Models\School;
use Illuminate\Support\Facades\Storage;
use Mpdf\Output\Destination;

/**
 * Bulletins du primaire et de la maternelle, portage de term_reports.php
 * (archange).
 *
 * La grille diffère de celle du secondaire : chaque matière occupe autant de
 * lignes qu'elle a de volets évalués (oral, écrit, savoir-être, et pratique le
 * cas échéant), avec une colonne par séquence. La note trimestrielle et
 * l'appréciation par compétence sont fusionnées sur la hauteur de la matière,
 * et le barème est rappelé sous son nom (« Sur/Over 50 »).
 */
class BulletinPrimaireGenerator
{
    private const OR = '#d8a02e';

    private const ARDOISE = '#292F36';

    /** Code couleur des appréciations par compétence. */
    private const COULEURS_APPRECIATION = [
        'A+' => '#15803d',
        'A' => '#166534',
        'ECA' => '#b45309',
        'NA' => '#dc2626',
    ];

    public function build(array $donnees): string
    {
        $mpdf = MpdfFactory::make([
            'orientation' => 'P',
            'margin_top' => 8,
            'margin_bottom' => 8,
        ]);
        $mpdf->SetTitle('Bulletins '.$donnees['classe']->nom.' — '.$donnees['trimestre']->libelle);

        $mpdf->WriteHTML($this->html($donnees));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function html(array $donnees): string
    {
        $pages = [];

        foreach ($donnees['eleves'] as $bulletin) {
            $pages[] = $this->header($donnees)
                .$this->infosEleve($bulletin, $donnees)
                .$this->tableauNotes($bulletin, $donnees)
                .$this->synthese($bulletin, $donnees)
                .$this->signatures($donnees);
        }

        if ($pages === []) {
            $pages[] = $this->header($donnees)
                .'<p style="text-align:center;margin-top:20mm;">Aucun élève actif dans cette classe.</p>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'.$this->styles().'</head><body>'
            .implode('<pagebreak />', $pages)
            .'</body></html>';
    }

    private function styles(): string
    {
        return '<style>'
            .'body{font-family:dejavusans,sans-serif;font-size:3.1mm;margin:0;padding:0;color:#333}'
            .'table{width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:6px}'
            .'th,td{border:0.5px solid #bdc3c7;text-align:center;padding:1.2px}'
            .'th{background-color:'.self::OR.';color:#fff;font-weight:bold;font-size:2.6mm}'
            .'.header-table{width:100%;table-layout:fixed;margin-bottom:6px}'
            .'.header-table td{text-align:center;vertical-align:top;border:none;font-size:2.5mm}'
            .'.lh-1{line-height:1.25}'
            .'.logo{width:64px}'
            .'.no-border,.no-border td,.no-border tr{border:none!important}'
            .'.titre{color:'.self::OR.';text-transform:uppercase;font-weight:bold;font-size:3.8mm}'
            .'.titre-en{color:'.self::OR.';text-transform:uppercase;font-style:italic;font-size:3.2mm}'
            .'.left{text-align:left!important}'
            .'.value{font-weight:bold;color:#000}'
            .'.mini{font-size:2.3mm}'
            .'.matiere{font-weight:bold;font-size:2.6mm}'
            .'.bareme{font-size:2.1mm;font-style:italic;color:#666}'
            .'.volet{font-size:2.4mm;background:#f6f8f6}'
            .'.note-trim{font-weight:bold;font-size:3mm;background:#f1f5f7}'
            .'.total-row td{background:'.self::ARDOISE.';color:#fff;font-weight:bold}'
            .'.appr{font-weight:bold;font-size:2.8mm}'
            .'</style>';
    }

    /** En-tête bilingue à trois colonnes : mentions FR, logo, mentions EN. */
    private function header(array $donnees): string
    {
        $school = $donnees['school'];

        $logo = $this->cheminImage($school->logo_path);
        $celluleLogo = $logo !== null
            ? '<img src="'.$this->e($logo).'" class="logo">'
            : '<div class="value" style="font-size:5mm;color:'.self::OR.';">'.$this->e($this->monogramme($school)).'</div>';

        return '<table class="header-table"><tr>'
            .'<td style="width:40%;"><div class="lh-1">'.nl2br($this->e($school->header_fr ?? $school->name)).'</div></td>'
            .'<td style="width:20%;">'.$celluleLogo.'</td>'
            .'<td style="width:40%;"><div class="lh-1">'.nl2br($this->e($school->header_en ?? $school->name)).'</div></td>'
            .'</tr></table>'
            .'<table class="no-border"><tr><td class="left" style="line-height:1.4;">'
            .'<span class="titre">Bulletin de notes du '.$this->e($donnees['trimestre']->libelle).'</span><br>'
            .'<span class="titre-en">'.$this->e($donnees['trimestre']->libelle).' report card</span><br>'
            .'<span style="font-size:2.8mm;">Année scolaire <i>/ Academic year</i> : <b>'
            .$this->e($donnees['annee']?->libelle ?? '—').'</b></span>'
            .'</td></tr></table>';
    }

    private function infosEleve(array $bulletin, array $donnees): string
    {
        $eleve = $bulletin['eleve'];
        $classe = $donnees['classe'];
        $effectif = $donnees['effectif'];

        $photo = $this->cheminImage($eleve->photo_path);
        $cellulephoto = $photo !== null
            ? '<img style="width:40px;height:40px;border:1px solid gray;" src="'.$this->e($photo).'">'
            : '<div style="width:40px;height:40px;border:1px solid gray;background:'.self::ARDOISE.';color:'.self::OR
                .';font-weight:bold;font-size:5mm;">'.$this->e($this->initiales($eleve)).'</div>';

        $bandeau = 'background:'.self::ARDOISE.';color:#fff;padding:1.5mm;';

        return '<table class="no-border" style="font-size:2.8mm;">'
            .'<tr><td class="left" style="width:40%;'.$bandeau.'">'
            .'<span style="color:#fff;">Nom de l\'élève <i>/ Student\'s name</i> :</span></td>'
            .'<td class="left" style="width:60%;'.$bandeau.'text-transform:uppercase;">'
            .'<b style="color:#fff;">'.$this->e($eleve->nomComplet()).'</b></td></tr>'
            .'<tr><td class="left" style="width:12%;">'.$cellulephoto.'</td>'
            .'<td class="left">'
            .'<table class="no-border" style="font-size:2.6mm;"><tr>'
            .$this->champ('Né(e) le', 'Born on', $eleve->date_naissance?->format('d/m/Y'))
            .$this->champ('À', 'At', $eleve->lieu_naissance)
            .$this->champ('Classe', 'Class', $classe->nom)
            .'</tr><tr>'
            .$this->champ('Matricule', 'ID', $eleve->matricule)
            .$this->champ('Sexe', 'Gender', $eleve->sexe)
            .$this->champ('Effectif', 'Enrolment',
                'M: '.$effectif['garcons'].'  F: '.$effectif['filles'].'  T: '.$effectif['total'])
            .'</tr><tr>'
            .$this->champ('Niveau', 'Level', $classe->niveauScolaire?->libelle)
            .$this->champ('Titulaire', 'Class teacher', $classe->titulaire?->nomComplet())
            .$this->champ('Statut', 'Status', $eleve->redoublant ? 'Redoublant(e)' : 'Passant(e)')
            .'</tr></table>'
            .'</td></tr></table>';
    }

    private function champ(string $fr, string $en, ?string $valeur): string
    {
        return '<td class="left" style="border:none;padding:0.5mm;">'
            .'<span>'.$this->e($fr).' <i>/ '.$this->e($en).'</i> : </span>'
            .'<span class="value">'.$this->e($valeur ?: '—').'</span></td>';
    }

    /**
     * Grille de notes : une ligne par volet d'évaluation, la note trimestrielle
     * et l'appréciation fusionnées sur la hauteur de la matière.
     */
    private function tableauNotes(array $bulletin, array $donnees): string
    {
        $sequences = $donnees['sequences'];
        $nbSequences = $sequences->count();

        $entete = '<tr><th class="left" style="width:32%;">Matières<br><i>Subjects</i></th>'
            .'<th style="width:16%;">Évaluation<br><i>Assessment</i></th>';
        foreach ($sequences as $sequence) {
            $entete .= '<th>'.$this->e($sequence->libelle).'</th>';
        }
        $entete .= '<th style="width:12%;">Note trim.<br><i>Term mark</i></th>'
            .'<th style="width:12%;">Appréciation<br><i>Remark</i></th></tr>';

        $corps = '';
        foreach ($bulletin['lignes'] as $ligne) {
            $nbVolets = max(count($ligne['volets']), 1);
            $couleur = self::COULEURS_APPRECIATION[$ligne['appreciation']] ?? '#333';

            foreach ($ligne['volets'] as $index => $volet) {
                $corps .= '<tr>';

                if ($index === 0) {
                    $corps .= '<td class="left" rowspan="'.$nbVolets.'">'
                        .'<span class="matiere">'.$this->e($ligne['matiere']).'</span><br>'
                        .'<span class="bareme">Sur / Over '.$ligne['bareme'].'</span></td>';
                }

                $corps .= '<td class="volet left">'.$this->e($volet['libelle'])
                    .' <i style="font-size:2.1mm;color:#777;">/ '.$this->e($volet['libelle_en']).'</i></td>';

                foreach ($volet['notes'] as $note) {
                    $corps .= '<td>'.$this->nombre($note).'</td>';
                }

                if ($index === 0) {
                    $corps .= '<td class="note-trim" rowspan="'.$nbVolets.'">'.$this->nombre($ligne['note']).'</td>'
                        .'<td class="appr" rowspan="'.$nbVolets.'" style="color:'.$couleur.';">'
                        .$this->e($ligne['appreciation']).'</td>';
                }

                $corps .= '</tr>';
            }
        }

        // Ligne de synthèse : totaux par séquence ramenés sur 20, puis le total
        // obtenu sur le barème cumulé de toutes les matières.
        $pied = '<tr class="total-row"><td class="left" colspan="2">'
            .'Moyenne par séquence <i>/ Average per sequence</i></td>';
        foreach ($bulletin['moyennes_sequences'] as $moyenne) {
            $pied .= '<td>'.$this->nombre($moyenne).'</td>';
        }
        $pied .= '<td colspan="2">Total : '.$this->nombre($bulletin['total_obtenu'])
            .' / '.$bulletin['total_bareme'].'</td></tr>';

        return '<table><thead>'.$entete.'</thead><tbody>'.$corps.$pied.'</tbody></table>'
            .'<span class="mini" style="display:block;text-align:right;color:#666;">'
            .'Chaque séquence totalise les volets évalués ('.$nbSequences.' séquences ce trimestre). '
            .'A+ ≥ 80 % · A ≥ 60 % · ECA ≥ 50 % · NA &lt; 50 % du barème.</span>';
    }

    private function synthese(array $bulletin, array $donnees): string
    {
        $stats = $donnees['stats'];

        return '<table style="font-size:2.7mm;"><tr>'
            .'<th style="width:25%;">Moyenne générale<br><i>Overall average</i></th>'
            .'<th style="width:15%;">Rang<br><i>Position</i></th>'
            .'<th style="width:20%;">Appréciation<br><i>Remark</i></th>'
            .'<th style="width:20%;">Absences (h)<br><i>Absences</i></th>'
            .'<th style="width:20%;">Profil de classe<br><i>Class profile</i></th>'
            .'</tr><tr>'
            .'<td style="font-size:4mm;font-weight:bold;color:'.self::OR.';">'
            .$this->nombre($bulletin['moyenne_generale']).' / 20</td>'
            .'<td class="value">'.($bulletin['rang'] ?? '—').' / '.$donnees['effectif']['total'].'</td>'
            .'<td class="value">'.$this->e($bulletin['appreciation_generale']).'</td>'
            .'<td>Just. : '.$this->nombre($bulletin['heures_justifiees'], 1)
            .'<br>Non just. : '.$this->nombre($bulletin['heures_non_justifiees'], 1).'</td>'
            .'<td class="mini">Moy. classe : '.$this->nombre($stats['moyenne_classe'])
            .'<br>Max : '.$this->nombre($stats['premier']).' · Min : '.$this->nombre($stats['dernier'])
            .'<br>Admis : '.$stats['admis'].' / '.$stats['evalues'].'</td>'
            .'</tr></table>';
    }

    private function signatures(array $donnees): string
    {
        $cachet = $this->cheminImage($donnees['school']->stamp_path);
        $celluleCachet = $cachet !== null
            ? '<img src="'.$this->e($cachet).'" style="width:60px;height:60px;">'
            : '';

        return '<table class="no-border" style="margin-top:8mm;font-size:2.7mm;"><tr>'
            .'<td style="width:33%;text-align:center;border:none;">'
            .'<b>Le Titulaire</b><br><i>Class teacher</i><br><br><br>_______________</td>'
            .'<td style="width:34%;text-align:center;border:none;">'.$celluleCachet.'</td>'
            .'<td style="width:33%;text-align:center;border:none;">'
            .'<b>Le Directeur</b><br><i>Head teacher</i><br><br><br>_______________</td>'
            .'</tr></table>';
    }

    private function monogramme(School $school): string
    {
        preg_match_all('/\b\p{L}/u', $school->name, $matches);

        return mb_strtoupper(implode('', array_slice($matches[0], 0, 3)));
    }

    private function initiales($eleve): string
    {
        return mb_strtoupper(mb_substr($eleve->prenom, 0, 1).mb_substr($eleve->nom, 0, 1));
    }

    /** Chemin absolu d'une image du disque public, ou null si elle n'existe pas. */
    private function cheminImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $absolu = Storage::disk('public')->path($path);

        return is_file($absolu) ? $absolu : null;
    }

    private function nombre(?float $valeur, int $decimales = 2): string
    {
        return $valeur === null ? '—' : number_format($valeur, $decimales, ',', ' ');
    }

    private function e(?string $valeur): string
    {
        return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
