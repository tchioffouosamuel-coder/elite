<?php

namespace App\Support\Pdf;

use App\Models\Eleve;
use App\Models\School;
use App\Models\Trimestre;
use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use App\Support\SignatureBulletin;
use Endroid\QrCode\Builder\Builder;
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
    use RenduDocument;

    public function build(array $donnees): string
    {
        $mpdf = MpdfFactory::make([
            'orientation' => 'P',
            'margin_top' => 8,
            'margin_bottom' => 8,
        ], $donnees['school']);
        $mpdf->SetTitle('Bulletins '.$donnees['classe']->nom.' — '.$donnees['trimestre']->libelle);

        // Filigrane plus marqué que sur les autres documents : une page aussi
        // dense en tableaux porte mieux un repère visuel large qu'un document
        // court comme le PV de conseil ou le bilan disciplinaire.
        MpdfFactory::appliquerFiligrane($mpdf, $donnees['school'], largeurMm: 150);

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
                .$this->tableauRappel($bulletin, $donnees['school'])
                .$this->qrAuthenticite($bulletin['eleve'], $donnees['trimestre']);
        }

        if ($pages === []) {
            $pages[] = $this->header($donnees)
                .'<p style="text-align:center;margin-top:20mm;">Aucun élève actif dans cette classe.</p>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'.$this->styles().'</head><body>'
            .implode('<pagebreak />', $pages)
            .'</body></html>';
    }

    /**
     * Le logo commun aux documents (`RenduDocument::LOGO_WIDTH`) domine trop la
     * page sur un bulletin, dense en tableaux : on le réduit ici sans toucher
     * au réglage partagé, qui reste adapté aux autres documents (PV, bilans…).
     */
    private const LOGO_WIDTH_BULLETIN = '85px';

    private function styles(): string
    {
        return '<style>'.$this->stylesBase().'.logo{width:'.self::LOGO_WIDTH_BULLETIN.';}</style>';
    }

    /** En-tête bilingue à trois colonnes : mentions FR, logo, mentions EN. */
    private function header(array $donnees): string
    {
        $school = $donnees['school'];

        return $this->enTeteEcole($school)
            .'<table class="no-border"><tr><td style="line-height:1.4;text-align:center;">'
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
            : '<div style="width:40px;height:40px;border:1px solid gray;background:'.self::ARDOISE.';color:'.self::ACCENT
                .';font-weight:bold;font-size:5mm;">'.$this->e($this->initiales($eleve)).'</div>';

        // mPDF ne propage pas la couleur posée sur <tr> jusqu'au contenu des
        // cellules : le fond et la couleur du bandeau sont répétés sur chaque td.
        $bandeau = 'background:'.self::ARDOISE.';color:#fff;padding:1.5mm;';

        return '<table class="no-border" style="font-size:2.8mm;" width="100%">'
            .'<tr><td class="left" style="width:40%;'.$bandeau.'">'
            .'<span style="color:#fff;">Nom de l\'élève <i>/ Student\'s name</i> :</span></td>'
            .'<td class="left" style="width:60%;'.$bandeau.'text-transform:uppercase;">'
            .'<b style="color:#fff;font-size:4.2mm;">'.$this->e($eleve->nom_complet).'</b></td></tr>'
            .'<tr><td class="left" style="width:10%;">'.$cellulephoto.'</td>'
            // mPDF ne fiabilise la largeur d'un tableau imbriqué que si elle est
            // posée en attribut HTML : la seule CSS `width:100%` de la classe
            // laissait ce bloc rétrécir à la largeur de son contenu, avec une
            // bande vide à droite de la page.
            .'<td class="left" style="width:90%;" width="90%">'
            // Trois champs par ligne à largeur explicite (1/3 chacun) : sans ça,
            // chaque cellule ne prend que la largeur de son texte et laisse une
            // bande vide à droite de la ligne au lieu de se répartir sur toute
            // la largeur du bloc.
            .'<table class="no-border" style="font-size:3.1mm;" width="100%"><tr>'
            .$this->champ('Né(e) le', 'Born on', $eleve->date_naissance?->format('d/m/Y'), 32)
            .$this->champ('À', 'At', $eleve->lieu_naissance, 30)
            .$this->champ('Classe', 'Class', $classe->nom, 38)
            .'</tr><tr>'
            .$this->champ('Matricule', 'ID', $eleve->matricule, 30)
            .$this->champ('Sexe', 'Gender', $eleve->sexe, 18)
            .$this->champ('Effectif', 'Enrolment',
                'M: '.$effectif['garcons'].'  F: '.$effectif['filles'].'  T: '.$effectif['total'], 52)
            .'</tr><tr>'
            .$this->champ('Statut', 'Status', $eleve->redoublant ? 'Redoublant(e)' : 'Passant(e)', 22)
            .$this->champ('Prof. principal', 'Class master', $classe->professeurPrincipal?->nom_complet, 39)
            .$this->champ('Surveillant gén.', 'Discipline master', $classe->surveillantGeneral?->nom_complet, 39)
            .'</tr></table>'
            .'</td></tr></table>';
    }

    /**
     * Largeurs inégales plutôt que trois colonnes fixes à 33 % : un champ
     * court comme « Sexe » (M/F) n'a pas besoin du même espace qu'« Effectif »
     * (« M: 19 F: 1 T: 20 ») ou qu'un nom de professeur. La ligne pointillée
     * sous la valeur absorbe ensuite ce qui reste sans jamais paraître vide —
     * posée sur un `<span>` plutôt que sur le `<td>` : mPDF perd silencieusement
     * toute bordure de cellule dans un tableau imbriqué (vérifié isolément),
     * alors qu'un simple soulignement de texte y survit sans problème.
     */
    private function champ(string $fr, string $en, ?string $valeur, int $largeur = 33): string
    {
        return '<td class="left" style="padding:0.5mm;width:'.$largeur.'%;" width="'.$largeur.'%">'
            .'<span>'.$this->e($fr).' <i>/ '.$this->e($en).'</i> : </span>'
            .'<span class="value" style="border-bottom:0.3mm dotted #9aa0a6;">'.$this->e($valeur ?: '—').'</span></td>';
    }

    /**
     * Grille de notes : une colonne par séquence du trimestre, les matières
     * regroupées par « groupe » d'affichage comme dans le bulletin legacy.
     */
    private function tableauNotes(array $bulletin, array $donnees): string
    {
        $sequences = $donnees['sequences'];

        // « Eval. N » plutôt que le libellé brut de la séquence (« Séquence N ») :
        // plus court, ça libère de la place dans une colonne déjà étroite.
        $entete = '<tr><th class="left">Matières<br><i>Subjects</i></th>'
            .'<th>Compétences évaluées<br><i>Competencies</i></th>';
        foreach ($sequences as $sequence) {
            $entete .= '<th>Eval. '.$sequence->ordre.'</th>';
        }
        $ordreTrimestre = $donnees['trimestre']->ordre;
        $entete .= '<th>Trim '.$ordreTrimestre.'.<br><i>Term '.$ordreTrimestre.'</i></th><th>Coef</th><th>TxC</th>'
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
        // Le parent signe avant que le titulaire ne vise, comme sur le bulletin
        // papier : la case précède donc « Prof. principal » plutôt que de la suivre.
        $entete .= '<th rowspan="'.($nbLignes + 3).'" style="width:13%;">Signature Parent</th>'
            .'<th rowspan="'.($nbLignes + 3).'" style="width:13%;">Prof. principal<br><i>Class master</i></th>'
            .'<th rowspan="'.($nbLignes + 3).'" style="width:13%;">Visa chef d\'établ.<br><i>Principal\'s visa</i>'
            .$this->visa($school).'</th></tr><tr>';
        foreach ($trimestres as $unused) {
            $entete .= '<th>Moy<i>/Av</i></th><th>Rang<i>/Pos</i></th>';
        }
        $entete .= '</tr>';

        $corps = '';
        for ($i = 0; $i < $nbLignes; $i++) {
            // Même libellé « Eval. N » que la grille de notes — jamais le libellé
            // brut de la séquence, pour ne pas afficher deux vocabulaires différents
            // sur le même bulletin.
            $corps .= '<tr><th>Eval. '.($i + 1).'</th>';
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

    /**
     * Code QR pointant vers la page publique de vérification d'authenticité
     * (cf. `SignatureBulletin`) : dissuade la présentation d'un bulletin
     * falsifié à un tiers, sans rien stocker en base pour chaque bulletin émis.
     */
    private function qrAuthenticite(Eleve $eleve, Trimestre $trimestre): string
    {
        $qr = (new Builder)->build(
            data: SignatureBulletin::lienVerification($eleve->id, $trimestre->id),
            size: 140,
            margin: 2,
        );

        return '<table class="no-border" style="margin-top:1mm;"><tr>'
            .'<td style="width:12%;border:none;padding:0;"><img src="'.$qr->getDataUri().'" style="width:16mm;height:16mm;"></td>'
            .'<td style="border:none;padding:0 0 0 2mm;font-size:2.3mm;color:'.self::ARDOISE.';">'
            .'Authenticité <i>/ Authenticity</i> : scannez ce code pour vérifier ce bulletin en ligne.<br>'
            .'<i>Scan this code to verify this report card online.</i></td>'
            .'</tr></table>';
    }

    /** Cachet et signature composés en une image (la signature traverse le cachet), dans le cartouche de visa. */
    private function visa(School $school): string
    {
        $visa = (new VisaComposeService)->chemin($school);

        return $visa !== null ? '<img src="'.$this->e($visa).'" style="height:40px;">' : '';
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

    private function initiales($eleve): string
    {
        return self::initialesDe($eleve->nom_complet);
    }

    /**
     * Initiales des deux premiers mots du nom complet (même règle que le
     * frontend, cf. PhotoCell) : le nom n'étant plus découpé en base, on
     * retombe sur la première lettre de chacun des deux premiers mots.
     */
    public static function initialesDe(?string $nomComplet): string
    {
        $mots = preg_split('/\s+/', trim((string) $nomComplet), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(
            fn (string $mot) => mb_substr($mot, 0, 1),
            array_slice($mots, 0, 2)
        )));
    }

    /** Chemin absolu d'une image du disque public, ou null si elle n'existe pas. */
}
