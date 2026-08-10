<?php

namespace App\Support\Pdf;

use App\Models\School;
use Illuminate\Support\Facades\Storage;
use Mpdf\Output\Destination;

/**
 * Bulletins de notes, portage de report_cards_single.php (_smapp).
 *
 * Comme le legacy, le HTML est assemblé en PHP puis rendu par mPDF, et un seul
 * document couvre toute la classe : un élève par page, séparés par <pagebreak/>.
 * On ne passe pas par Blade — la mise en page dépend de calculs (colonnes de
 * séquences variables, groupes de matières) que le legacy fait au fil de la
 * construction de la chaîne.
 */
class BulletinGenerator
{
    private const OR = '#d8a02e';

    private const ARDOISE = '#292F36';

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
                .$this->tableauSynthese($bulletin, $donnees)
                .$this->tableauRappel($bulletin, $donnees['school']);
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
            .'body{font-family:dejavusans,sans-serif;font-size:3.2mm;margin:0;padding:0;color:#333}'
            .'table{width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:6px}'
            .'th,td{border:0.5px solid #bdc3c7;text-align:center;padding:1px}'
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
            .'.mini{font-size:2.2mm}'
            .'.legende{font-size:2.1mm;text-align:center;display:block}'
            .'.rouge{color:#ac3527;font-weight:bold}'
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

        // mPDF ne propage pas la couleur posée sur <tr> jusqu'au contenu des
        // cellules : le fond et la couleur du bandeau sont répétés sur chaque td.
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
            .$this->champ('Statut', 'Status', $eleve->redoublant ? 'Redoublant(e)' : 'Passant(e)')
            .$this->champ('Prof. principal', 'Class master', $classe->professeurPrincipal?->nomComplet())
            .$this->champ('Surveillant gén.', 'Discipline master', $classe->surveillantGeneral?->nomComplet())
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
     * Grille de notes : une colonne par séquence du trimestre, les matières
     * regroupées par « groupe » d'affichage comme dans le bulletin legacy.
     */
    private function tableauNotes(array $bulletin, array $donnees): string
    {
        $sequences = $donnees['sequences'];

        $entete = '<tr><th class="left">Matières<br><i>Subjects</i></th>'
            .'<th>Compétences évaluées<br><i>Competencies</i></th>';
        foreach ($sequences as $sequence) {
            $entete .= '<th>'.$this->e($sequence->libelle).'</th>';
        }
        $entete .= '<th>Trim.<br><i>Term</i></th><th>Coef</th><th>TxC</th>'
            .'<th>Cote<br><i>Grade</i></th><th>Min</th><th>Max</th><th>Rang<br><i>Pos.</i></th>'
            .'<th>Obs. &amp; Sign.</th></tr>';

        $corps = '';
        foreach ($bulletin['groupes'] as $lignes) {
            foreach ($lignes as $ligne) {
                $corps .= '<tr>'
                    .'<td class="left mini"><b style="font-size:2.5mm;">'.$this->e($ligne['matiere']).'</b><br>'
                    .'<span>'.$this->e($ligne['enseignant']).'</span></td>'
                    .'<td class="left mini">'.$this->competences($ligne['competences']).'</td>';

                foreach ($ligne['notes'] as $note) {
                    $corps .= '<td>'.$this->nombre($note).'</td>';
                }

                $corps .= '<th>'.$this->nombre($ligne['moyenne']).'</th>'
                    .'<td>'.$this->nombre($ligne['coefficient'], 1).'</td>'
                    .'<th>'.$this->nombre($ligne['total']).'</th>'
                    .'<td><b>'.$this->e($ligne['cote']).'</b></td>'
                    .'<td>'.$this->nombre($ligne['min']).'</td>'
                    .'<td>'.$this->nombre($ligne['max']).'</td>'
                    .'<td>'.$this->e((string) ($ligne['rang'] ?? '—')).'</td>'
                    .'<td></td></tr>';
            }
        }

        return '<table>'.$entete.$corps.'</table>';
    }

    private function competences(array $competences): string
    {
        return implode('<br>', array_map(fn ($c) => '- '.$this->e($c), $competences));
    }

    private function tableauSynthese(array $bulletin, array $donnees): string
    {
        $stats = $donnees['stats'];

        $sanctions = $bulletin['sanctions']->isNotEmpty()
            ? $bulletin['sanctions']->map(fn ($s) => $s->motif)->implode(', ')
            : 'Aucune';

        $distinction = collect([
            $this->libelleMention($bulletin['mention_travail']),
            $this->libelleMention($bulletin['mention_conduite']),
        ])->filter()->implode(' · ') ?: '—';

        return '<table><tr>'
            .'<th style="width:25%;">Travail <i>/ Work</i></th>'
            .'<th style="width:20%;">Conduite <i>/ Conduct</i></th>'
            .'<th style="width:35%;">Appréciations <i>/ Remarks</i></th>'
            .'<th style="width:20%;">Profil classe <i>/ Class perf.</i></th>'
            .'</tr><tr>'
            .'<td class="left mini">'
            .'Total points : <b>'.$this->nombre($bulletin['total_points']).'</b><br>'
            .'Total coef : <b>'.$this->nombre($bulletin['total_coef'], 1).'</b><br>'
            .'Moyenne <i>/ Av</i> : <b>'.$this->nombre($bulletin['moyenne_generale']).'</b><br>'
            .'Rang <i>/ Rank</i> : <b>'.$this->e((string) ($bulletin['rang'] ?? '—')).'</b><br>'
            .'Cote <i>/ Grade</i> : <b>'.$this->e($bulletin['cote']).'</b></td>'
            .'<td class="left mini">'
            .'Absences NJ : <b>'.$this->nombre($bulletin['heures_non_justifiees'], 1).' h</b><br>'
            .'Absences J : <b>'.$this->nombre($bulletin['heures_justifiees'], 1).' h</b><br>'
            .'Sanction(s) : <span class="rouge">'.$this->e($sanctions).'</span></td>'
            .'<td class="left mini">'
            .'Appréciation <i>/ Remark</i> : <b>'.$this->e($this->libelleAppreciation($bulletin['appreciation'])).'</b><br>'
            .'Distinction : <b>'.$this->e($distinction).'</b><br>'
            .'Conseil <i>/ Advice</i> :<br><b>'.$this->e($bulletin['conseil']).'</b></td>'
            .'<td class="left mini">'
            .'Évalués <i>/ Rated</i> : <b>'.$stats['evalues'].'</b><br>'
            .'Moy <i>/ Av</i> ≥ 10 : <b>'.$stats['sup10'].' ('.$stats['pourcentage_reussite'].'%)</b><br>'
            .'Premier <i>/ First</i> : <b>'.$this->nombre($stats['premier']).'</b><br>'
            .'Dernier <i>/ Last</i> : <b>'.$this->nombre($stats['dernier']).'</b><br>'
            .'MGC <i>/ Class av</i> : <b>'.$this->nombre($stats['moyenne_classe']).'</b></td>'
            .'</tr></table>';
    }

    /**
     * Rappel des moyennes de l'année (une colonne par trimestre, une ligne par
     * séquence) et cartouche de visas.
     */
    private function tableauRappel(array $bulletin, School $school): string
    {
        $trimestres = $bulletin['rappel'];
        $nbLignes = max(array_map(fn ($t) => count($t['sequences']), $trimestres) ?: [0]);

        $entete = '<tr><th rowspan="2">Éval.</th>';
        foreach ($trimestres as $trimestre) {
            $entete .= '<th colspan="2">'.$this->e($trimestre['libelle']).'</th>';
        }
        $entete .= '<th rowspan="'.($nbLignes + 3).'" style="width:14%;">Prof. principal<br><i>Class master</i></th>'
            .'<th rowspan="'.($nbLignes + 3).'" style="width:14%;">Visa chef d\'établ.<br><i>Principal\'s visa</i>'
            .$this->visa($school).'</th></tr><tr>';
        foreach ($trimestres as $unused) {
            $entete .= '<th>Moy<i>/Av</i></th><th>Rang<i>/Pos</i></th>';
        }
        $entete .= '</tr>';

        $corps = '';
        for ($i = 0; $i < $nbLignes; $i++) {
            $corps .= '<tr><th>'.$this->e($trimestres[0]['sequences'][$i]['libelle'] ?? ('Éval. '.($i + 1))).'</th>';
            foreach ($trimestres as $trimestre) {
                $sequence = $trimestre['sequences'][$i] ?? null;
                $corps .= '<td>'.$this->nombre($sequence['moyenne'] ?? null).'</td>'
                    .'<td>'.$this->e((string) ($sequence['rang'] ?? '—')).'</td>';
            }
            $corps .= '</tr>';
        }

        $corps .= '<tr><th>Trim. <i>/ Term</i></th>';
        foreach ($trimestres as $trimestre) {
            $corps .= '<th>'.$this->nombre($trimestre['trimestre']['moyenne']).'</th>'
                .'<th>'.$this->e((string) ($trimestre['trimestre']['rang'] ?? '—')).'</th>';
        }
        $corps .= '</tr>';

        return '<span class="legende">Compétences très bien acquises (<b>CTBA</b>) — bien acquises (<b>CBA</b>) — '
            .'acquises (<b>CA</b>) — moyennement acquises (<b>CMA</b>) — non acquises (<b>CNA</b>)</span>'
            .'<table>'.$entete.$corps.'</table>';
    }

    /** Cachet et signature scannés de l'établissement, superposés dans le cartouche de visa. */
    private function visa(School $school): string
    {
        $images = array_filter([
            $this->cheminImage($school->signature_path),
            $this->cheminImage($school->stamp_path),
        ]);

        return implode('', array_map(
            fn ($chemin) => '<div><img src="'.$this->e($chemin).'" style="height:36px;"></div>',
            $images
        ));
    }

    private function libelleAppreciation(string $code): string
    {
        return match ($code) {
            'tres_bien' => 'Très bien / CTBA',
            'bien' => 'Bien / CBA',
            'assez_bien' => 'Assez bien / CA',
            'passable' => 'Passable / CMA',
            'insuffisant' => 'Insuffisant / CNA',
            default => '—',
        };
    }

    private function libelleMention(?string $code): ?string
    {
        return match ($code) {
            'felicitations' => 'Félicitations',
            'encouragements' => 'Encouragements',
            'avertissement_travail' => 'Avertissement travail',
            'blame_travail' => 'Blâme travail',
            'avertissement_conduite' => 'Avertissement conduite',
            'blame_conduite' => 'Blâme conduite',
            default => null,
        };
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
