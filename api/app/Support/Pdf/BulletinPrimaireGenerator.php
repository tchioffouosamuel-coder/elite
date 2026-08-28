<?php

namespace App\Support\Pdf;

use App\Services\VisaComposeService;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Bulletins du primaire et de la maternelle, portage de term_reports.php
 * (archange) sur le modèle du bulletin trimestriel de l'École Primaire
 * Bilingue Laïque Les Élites : bandeau vert, une colonne « Total » fusionnée
 * par séquence en plus des notes brutes par volet, et un pied de bulletin en
 * trois blocs (codes d'appréciation + signatures, récapitulatif + décision,
 * observations + statistiques de classe).
 *
 * La grille diffère de celle du secondaire : chaque matière occupe autant de
 * lignes qu'elle a de volets évalués (oral, écrit, savoir-être, et pratique le
 * cas échéant), avec deux colonnes par séquence — la note du volet et le
 * total de la matière pour cette séquence, fusionné sur la hauteur du bloc.
 * La note trimestrielle et l'appréciation par compétence sont elles aussi
 * fusionnées sur la hauteur de la matière.
 */
class BulletinPrimaireGenerator
{
    use RenduDocument;

    private const VERT = '#92d050';

    /**
     * Accent violet propre à ce bulletin : la constante ACCENT de RenduDocument
     * reste partagée avec les autres documents (bulletin du secondaire, fiche
     * du personnel, statistiques) — un simple const violet local permet de la
     * remplacer ici sans les affecter.
     */
    private const VIOLET = '#672D92';

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
        ], $donnees['school']);
        $mpdf->SetTitle('Bulletins ' . $donnees['classe']->nom . ' — ' . $donnees['trimestre']->libelle);

        $mpdf->WriteHTML($this->html($donnees));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function html(array $donnees): string
    {
        $pages = [];

        $parAppreciation = ($donnees['mode'] ?? 'note') === 'appreciation';

        foreach ($donnees['eleves'] as $bulletin) {
            $pages[] = $this->enTeteEcole($donnees['school'])
                . $this->titre($donnees)
                . $this->infosEleve($bulletin, $donnees)
                . ($parAppreciation
                    ? $this->tableauAppreciations($bulletin, $donnees)
                    : $this->tableauNotes($bulletin, $donnees))
                . ($parAppreciation
                    // Ni moyenne, ni rang, ni récapitulatif : la maternelle ne
                    // classe pas ses élèves. Restent la légende et les signatures.
                    ? $this->piedMaternelle($bulletin, $donnees)
                    : $this->pied($bulletin, $donnees));
        }

        if ($pages === []) {
            $pages[] = $this->enTeteEcole($donnees['school'])
                . '<p style="text-align:center;margin-top:20mm;">Aucun élève actif dans cette classe.</p>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $this->styles() . '</head><body>'
            . implode('<pagebreak />', $pages)
            . '</body></html>';
    }

    private function styles(): string
    {
        return '<style>' . $this->stylesBase()
            // Accent violet propre à ce bulletin, à la place de l'accent
            // partagé par les autres documents (cf. const VIOLET).
            . '.titre{color:' . self::VIOLET . ';}'
            . '.titre-en{color:' . self::VIOLET . ';}'

            . 'table.grille td,table.grille th{font-size:2.9mm;border-color:#000;}'
            . 'table.grille td{color:#000;}'
            . 'table.grille th{background-color:' . self::VERT . ';color:#000;}'
            . '.matiere-cell{padding:1.5mm;}'
            . '.matiere{font-weight:bold;font-size:2.9mm;}'
            . '.bareme{font-size:2mm;font-style:italic;color:#666;}'
            . '.volet{background:#f6f8f6;}'
            . '.fusion{background:#eef3ee;font-weight:bold;}'
            . '.note-trim{font-weight:bold;font-size:2.8mm;background:#f1f5f7;}'
            . '.appr{font-weight:bold;}'
            . '.zone{background:#fff;height:9mm;vertical-align:top;}'
            . '.total-row td{background:' . self::ARDOISE . ';color:#fff;font-weight:bold;}'
            . '.bandeau{background:' . self::ARDOISE . ';color:#fff;padding:1.4mm;}'
            . '.photo-frame{border:0.35mm dashed ' . self::VIOLET . ';text-align:center;vertical-align:middle;font-weight:bold;color:' . self::VIOLET . ';}'
            . 'table.pied-bloc{width:100%;border-collapse:collapse;margin:0 0 2mm 0;}'
            . 'table.pied-bloc td,table.pied-bloc th{border:0.4px solid #999;padding:1mm;font-size:2.2mm;}'
            . 'table.pied-bloc th{background-color:' . self::VERT . ';color:#000;}'
            . '.codes-cell{text-align:left;padding:1.5mm;}'
            . '.codes-titre{font-weight:bold;text-decoration:underline;margin-bottom:0.6mm;}'
            . '.codes-l{line-height:1.4;}'
            . '.sign-lbl{text-align:center;font-weight:bold;}'
            . '.pied-lbl{text-align:left;font-weight:bold;padding-left:1.5mm;}'
            . '.decision{margin-top:1mm;text-align:center;font-weight:bold;border:0.4px solid #999;padding:1.5mm;background:#f6f8f6;}'
            . '.th-pied{text-align:center;font-weight:bold;}'
            . '.zone-obs{height:14mm;vertical-align:top;}'
            . '.stat-lbl{text-align:left;font-weight:bold;padding-left:1.5mm;}'
            . '.stat-val{text-align:right;padding-right:1.5mm;}'
            . '</style>';
    }

    /** Titre bilingue du bulletin et année académique, centré. */
    private function titre(array $donnees): string
    {
        $ordinal = $this->ordinalTrimestre((int) ($donnees['trimestre']->ordre ?? 1));

        return '<table class="no-border" style="margin-bottom:2mm;"><tr><td style="border:none;line-height:1.4;text-align:center;">'
            . '<span class="titre">Bulletin de notes du ' . $ordinal['fr'] . ' Trimestre</span><br>'
            . '<span class="titre-en">' . $ordinal['en'] . ' Term Report Card</span><br>'
            . '<span style="font-size:2.8mm;">Année scolaire <i>/ Academic year</i> : <b>'
            . $this->e($donnees['annee']?->libelle ?? '—') . '</b></span>'
            . '</td></tr></table>';
    }

    /**
     * Libellés ordinaux du trimestre (Premier/Deuxième/Troisième), plus
     * fidèles au bulletin officiel que le libellé brut « Trimestre 1 ».
     *
     * @return array{fr: string, en: string}
     */
    private function ordinalTrimestre(int $ordre): array
    {
        return [
            'fr' => match ($ordre) {
                1 => 'Premier',
                2 => 'Deuxième',
                3 => 'Troisième',
                default => $ordre . 'e',
            },
            'en' => match ($ordre) {
                1 => 'First',
                2 => 'Second',
                3 => 'Third',
                default => $ordre . 'th',
            },
        ];
    }

    private function infosEleve(array $bulletin, array $donnees): string
    {
        $eleve = $bulletin['eleve'];
        $classe = $donnees['classe'];
        $effectif = $donnees['effectif'];

        // Une table à cellule unique, pas un <div> : mPDF applique width/height
        // de façon fiable sur les cellules de tableau, pas sur les blocs.
        $photo = $this->cheminImage($eleve->photo_path);
        $contenuPhoto = $photo !== null
            ? '<img style="width:20mm;height:24mm;" src="' . $this->e($photo) . '">'
            : 'PHOTO';
        $cellulePhoto = '<table class="no-border" style="width:20mm;"><tr>'
            . '<td class="photo-frame" style="width:20mm;height:24mm;">' . $contenuPhoto . '</td>'
            . '</tr></table>';

        return '<table class="no-border" style="font-size:2.8mm;">'
            . '<tr><td class="left bandeau" colspan="2">'
            . '<span style="color:#fff;">Nom de l\'élève <i>/ Student\'s name</i> :</span> '
            . '<b style="color:#fff;text-transform:uppercase;">' . $this->e($eleve->nom_complet) . '</b></td></tr>'
            . '<tr><td class="left" style="width:14%;vertical-align:top;">' . $cellulePhoto . '</td>'
            . '<td class="left">'
            . '<table class="no-border" style="font-size:2.6mm;"><tr>'
            . $this->champ('Né(e) le', 'Born on', $eleve->date_naissance?->format('d/m/Y'))
            . $this->champ('À', 'At', $eleve->lieu_naissance)
            . $this->champ('Sexe', 'Gender', $eleve->sexe)
            . '</tr><tr>'
            . $this->champ('Classe', 'Class', $classe->nom)
            . $this->champ('Matricule', 'ID', $eleve->matricule)
            . $this->champ('Statut', 'Status', $eleve->redoublant ? 'Redoublant(e)' : 'Passant(e)')
            . '</tr><tr>'
            // La maternelle ne range pas ses classes par degré : afficher un
            // « Niveau : — » y annoncerait un champ qui n'existe pas pour elle.
            . ($classe->niveauScolaire
                ? $this->champ('Niveau', 'Level', $classe->niveauScolaire->libelle)
                : '<td style="border:none;width:33%;"></td>')
            . $this->champ(
                'Effectif',
                'Enrolment',
                'M: ' . $effectif['garcons'] . '  F: ' . $effectif['filles'] . '  T: ' . $effectif['total']
            )
            . $this->champ('Enseignant', 'Teacher', $classe->titulaire?->nom_complet)
            . '</tr></table>'
            . '</td></tr></table>';
    }

    private function champ(string $fr, string $en, ?string $valeur): string
    {
        return '<td class="left" style="border:none;padding:0.5mm;width:33%;">'
            . '<span>' . $this->e($fr) . ' <i>/ ' . $this->e($en) . '</i> : </span>'
            . '<span class="value">' . $this->e($valeur ?: '—') . '</span></td>';
    }

    /**
     * Grille de notes : une ligne par volet d'évaluation. Chaque séquence
     * apporte deux colonnes — la note du volet, puis le total de la matière
     * pour cette séquence (fusionné sur la hauteur du bloc) — avant la note
     * trimestrielle et l'appréciation, elles aussi fusionnées.
     */
    /**
     * Grille de la maternelle : une colonne par niveau d'appréciation, en-tête
     * portant l'émoji du référentiel, et la case du niveau atteint remplie de
     * sa couleur.
     *
     * La couleur porte seule l'information à l'impression couleur ; on inscrit
     * aussi l'émoji dans la case remplie pour qu'une photocopie en noir et
     * blanc reste lisible — un bulletin circule souvent sous cette forme.
     */
    private function tableauAppreciations(array $bulletin, array $donnees): string
    {
        $appreciations = collect($donnees['appreciations'] ?? [])->values();

        if ($appreciations->isEmpty()) {
            return '<p style="text-align:center;">Aucun niveau d\'appréciation n\'est configuré pour cet établissement.</p>';
        }

        // Colonne « Activités » élargie (les intitulés du référentiel sont
        // parfois longs) et colonnes de niveau resserrées : leur contenu
        // tient en une puce colorée, elles n'ont pas besoin de 16 % chacune.
        $wComp = 35;
        $wEval = 20;
        $wNiveau = max((int) round((100 - $wComp - $wEval) / max($appreciations->count(), 1)), 6);

        $entete = '<tr>'
            . '<th class="left" style="width:' . $wComp . '%;">Activités<br><i>Activities</i></th>'
            . '<th style="width:' . $wEval . '%;">Évaluation<br><i>Assessment</i></th>';

        foreach ($appreciations as $appreciation) {
            $entete .= '<th style="width:' . $wNiveau . '%;">'
                . ($appreciation->emoji ? '<span style="font-family:symbola;font-size:5mm;">' . $this->e($appreciation->emoji) . '</span><br>' : '')
                . '<span style="font-size:2.1mm;">' . $this->e($appreciation->label_fr) . '</span></th>';
        }

        $entete .= '</tr>';

        $corps = '';

        foreach ($bulletin['lignes'] as $ligne) {
            $nbVolets = max(count($ligne['volets']), 1);

            foreach ($ligne['volets'] as $index => $volet) {
                $corps .= '<tr>';

                if ($index === 0) {
                    $corps .= '<td class="left matiere-cell" rowspan="' . $nbVolets . '">'
                        . '<span class="matiere">' . $this->e($ligne['matiere']) . '</span>'
                        . ($ligne['matiere_en']
                            ? '<br><i style="font-size:2mm;color:#777;">' . $this->e($ligne['matiere_en']) . '</i>'
                            : '')
                        . '</td>';
                }

                $corps .= '<td class="volet left">' . $this->e($volet['libelle'])
                    . ' <i style="font-size:2.4mm;color:#777;">/ ' . $this->e($volet['libelle_en']) . '</i></td>';

                $atteinte = $volet['appreciation']['id'] ?? null;

                foreach ($appreciations as $appreciation) {
                    $corps .= $appreciation->id === $atteinte
                        ? '<td style="background-color:' . $this->e($appreciation->couleur) . ';color:#fff;font-family:symbola;font-size:4mm;">'
                            . $this->e($appreciation->emoji ?? '') . '</td>'
                        : '<td>&nbsp;</td>';
                }

                $corps .= '</tr>';
            }
        }

        return '<table class="grille"><thead>' . $entete . '</thead><tbody>' . $corps . '</tbody></table>';
    }

    /** Pied de la maternelle : la légende des niveaux, puis les signatures. */
    private function piedMaternelle(array $bulletin, array $donnees): string
    {
        $legende = '<div class="codes-titre">Codes d\'appréciation <i>/ Assessment codes</i></div>';

        foreach (collect($donnees['appreciations'] ?? []) as $appreciation) {
            $legende .= '<div class="codes-l">'
                . '<span style="color:' . $this->e($appreciation->couleur) . ';">■</span> '
                . ($appreciation->emoji ? '<span style="font-family:symbola;">' . $this->e($appreciation->emoji) . '</span> ' : '')
                . '<b>' . $this->e($appreciation->label_fr) . '</b>'
                . ($appreciation->label_en ? ' <i>/ ' . $this->e($appreciation->label_en) . '</i>' : '')
                . '</div>';
        }

        $absences = '<table class="pied-bloc"><tr><th colspan="2">Assiduité <i>/ Attendance</i></th></tr>'
            . '<tr><td class="left">Jours justifiés <i>/ Justified</i></td><td>' . (int) ($bulletin['jours_justifies'] ?? 0) . '</td></tr>'
            . '<tr><td class="left">Jours non justifiés <i>/ Unjustified</i></td><td>' . (int) ($bulletin['jours_non_justifies'] ?? 0) . '</td></tr>'
            . '</table>';

        $signatures = '<table class="pied-bloc">'
            . '<tr><td class="sign-lbl">L\'Enseignant<br><i>Class teacher</i></td>'
            . '<td class="sign-lbl">Parent</td></tr>'
            . '<tr><td class="zone">&nbsp;</td><td class="zone">&nbsp;</td></tr></table>';

        return '<table class="no-border"><tr>'
            . '<td style="width:40%;vertical-align:top;border:none;">'
            . '<table class="pied-bloc"><tr><td class="codes-cell">' . $legende . '</td></tr></table></td>'
            . '<td style="width:26%;vertical-align:top;border:none;padding:0 2mm;">' . $absences . '</td>'
            . '<td style="width:34%;vertical-align:top;border:none;">' . $signatures . '</td>'
            . '</tr></table>';
    }

    private function tableauNotes(array $bulletin, array $donnees): string
    {
        $sequences = $donnees['sequences']->values();
        $nb = max($sequences->count(), 1);

        $wComp = 17;
        $wEval = 13;
        $wSeqNote = 7;
        $wSeqTotal = 7;
        $wTrim = 9;
        $wAppr = 9;
        $wObs = max(100 - $wComp - $wEval - ($wSeqNote + $wSeqTotal) * $nb - $wTrim - $wAppr, 8);

        $entete = '<tr>'
            . '<th class="left" rowspan="2" style="width:' . $wComp . '%;">Matières<br><i>Subjects</i></th>'
            . '<th rowspan="2" style="width:' . $wEval . '%;">Évaluation<br><i>Assessment</i></th>';
        foreach ($sequences as $sequence) {
            $entete .= '<th colspan="2">' . $this->e($sequence->libelle) . '</th>';
        }
        $entete .= '<th rowspan="2" style="width:' . $wTrim . '%;">Note trim.<br><i>Term mark</i></th>'
            . '<th rowspan="2" style="width:' . $wAppr . '%;">Appréciation<br><i>Remark</i></th>'
            . '<th rowspan="2" style="width:' . $wObs . '%;">Observations<br><i>Remarks</i></th>'
            . '</tr><tr>';
        foreach ($sequences as $sequence) {
            $entete .= '<th style="width:' . $wSeqNote . '%;">Note</th><th style="width:' . $wSeqTotal . '%;">Total</th>';
        }
        $entete .= '</tr>';

        $corps = '';
        foreach ($bulletin['lignes'] as $ligne) {
            $nbVolets = max(count($ligne['volets']), 1);
            $couleur = self::COULEURS_APPRECIATION[$ligne['appreciation']] ?? '#333';

            foreach ($ligne['volets'] as $index => $volet) {
                $corps .= '<tr>';

                if ($index === 0) {
                    $corps .= '<td class="left matiere-cell" rowspan="' . $nbVolets . '">'
                        . '<span class="matiere">' . $this->e($ligne['matiere']) . '</span> '
                        . '<i style="font-size:2mm;color:#777;">/ ' . $this->e($ligne['matiere_en']) . '</i><br>'
                        . '<span class="bareme">Sur / Over ' . $ligne['bareme'] . '</span></td>';
                }

                $corps .= '<td class="volet left">' . $this->e($volet['libelle'])
                    . ' <i style="font-size:2.1mm;color:#777;">/ ' . $this->e($volet['libelle_en']) . '</i>'
                    . ($volet['bareme'] !== null
                        ? ' <span class="bareme">(/ ' . $this->baremeVolet($volet['bareme']) . ')</span>'
                        : '')
                    . '</td>';

                foreach ($sequences as $sIndex => $sequence) {
                    $corps .= '<td>' . $this->nombre($volet['notes'][$sIndex] ?? null) . '</td>';

                    if ($index === 0) {
                        $corps .= '<td class="fusion" rowspan="' . $nbVolets . '">'
                            . $this->nombre($ligne['totaux_sequences'][$sIndex] ?? null) . '</td>';
                    }
                }

                if ($index === 0) {
                    $corps .= '<td class="note-trim" rowspan="' . $nbVolets . '">' . $this->nombre($ligne['note']) . '</td>'
                        . '<td class="appr" rowspan="' . $nbVolets . '" style="color:' . $couleur . ';">'
                        . $this->e($ligne['appreciation']) . '</td>'
                        . '<td class="zone" rowspan="' . $nbVolets . '">&nbsp;</td>';
                }

                $corps .= '</tr>';
            }
        }

        // Ligne de synthèse : moyenne par séquence ramenée sur 20, puis le
        // total obtenu sur le barème cumulé de toutes les matières.
        $pied = '<tr class="total-row"><td class="left" colspan="2">'
            . 'Moyenne par séquence <i>/ Average per sequence</i></td>';
        foreach ($bulletin['moyennes_sequences'] as $moyenne) {
            $pied .= '<td colspan="2">' . $this->nombre($moyenne) . '</td>';
        }
        $pied .= '<td colspan="3">Total : ' . $this->nombre($bulletin['total_obtenu'])
            . ' / ' . $bulletin['total_bareme'] . '</td></tr>';

        return '<table class="grille"><thead>' . $entete . '</thead><tbody>' . $corps . $pied . '</tbody></table>';
    }

    /** Barème d'un volet, sans décimales inutiles (10 plutôt que 10,00 ; 7,5 pour un demi-point). */
    private function baremeVolet(float $bareme): string
    {
        return rtrim(rtrim(number_format($bareme, 2, ',', ' '), '0'), ',');
    }

    /** Pied de bulletin en trois blocs : codes/signatures, récap/décision, observations/statistiques. */
    private function pied(array $bulletin, array $donnees): string
    {
        return '<table class="no-border"><tr>'
            . '<td style="width:34%;vertical-align:top;border:none;">' . $this->piedGauche() . '</td>'
            . '<td style="width:32%;vertical-align:top;border:none;padding:0 2mm;">' . $this->piedRecap($bulletin, $donnees) . '</td>'
            . '<td style="width:34%;vertical-align:top;border:none;">' . $this->piedDroite($bulletin, $donnees) . '</td>'
            . '</tr></table>';
    }

    /** Légende des codes d'appréciation (mêmes seuils que MoyennePrimaireService::appreciationCompetence) et cartouches de signature. */
    private function piedGauche(): string
    {
        $codes = '<div class="codes-titre">Codes d\'appréciation <i>/ Assessment codes</i></div>'
            . '<div class="codes-l"><b>A+</b> Expert (≥ 80 % du barème)</div>'
            . '<div class="codes-l"><b>A</b> Compétence Acquise (≥ 60 %)</div>'
            . '<div class="codes-l"><b>ECA</b> En Cours d\'Acquisition (≥ 50 %)</div>'
            . '<div class="codes-l"><b>NA</b> Non Acquis (&lt; 50 %)</div>';

        return '<table class="pied-bloc"><tr><td colspan="2" class="codes-cell">' . $codes . '</td></tr>'
            . '<tr><td class="sign-lbl">L\'Enseignant<br><i>Class teacher</i></td>'
            . '<td class="sign-lbl">Parent</td></tr>'
            . '<tr><td class="zone">&nbsp;</td><td class="zone">&nbsp;</td></tr></table>';
    }

    /** Récapitulatif Total/Moyenne/Rang par séquence puis pour le trimestre, et appréciation générale. */
    private function piedRecap(array $bulletin, array $donnees): string
    {
        $sequences = $donnees['sequences']->values();

        $entete = '<tr><th class="left">&nbsp;</th>';
        foreach ($sequences as $sequence) {
            $entete .= '<th>' . $this->e($sequence->libelle) . '</th>';
        }
        $entete .= '<th>Trim.</th></tr>';

        $totalParSequence = array_fill(0, max($sequences->count(), 1), 0.0);
        foreach ($bulletin['lignes'] as $ligne) {
            foreach ($ligne['totaux_sequences'] as $i => $total) {
                $totalParSequence[$i] = ($totalParSequence[$i] ?? 0.0) + ($total ?? 0.0);
            }
        }

        $ligneTotal = '<tr><td class="pied-lbl">Total</td>';
        foreach ($totalParSequence as $total) {
            $ligneTotal .= '<td>' . $this->nombre($total) . '</td>';
        }
        $ligneTotal .= '<td>' . $this->nombre($bulletin['total_obtenu']) . '</td></tr>';

        $ligneMoy = '<tr><td class="pied-lbl">Moy. / <i>Avg</i></td>';
        foreach ($bulletin['moyennes_sequences'] as $moyenne) {
            $ligneMoy .= '<td>' . $this->nombre($moyenne) . '</td>';
        }
        $ligneMoy .= '<td class="value">' . $this->nombre($bulletin['moyenne_generale']) . '</td></tr>';

        // Le classement n'est calculé qu'au niveau du trimestre : pas de rang
        // par séquence isolée, pour ne pas fabriquer une donnée inexistante.
        $ligneRang = '<tr><td class="pied-lbl">Rang / <i>Rank</i></td>';
        foreach ($sequences as $sequence) {
            $ligneRang .= '<td>—</td>';
        }
        $ligneRang .= '<td class="value">' . ($bulletin['rang'] ?? '—') . ' / ' . $donnees['effectif']['total'] . '</td></tr>';

        return '<table class="pied-bloc">' . $entete . $ligneTotal . $ligneMoy . $ligneRang . '</table>'
            . '<div class="decision">' . $this->e($bulletin['appreciation_generale']) . '</div>';
    }

    /** Observations générales (zone libre), statistiques de classe et signature du directeur. */
    private function piedDroite(array $bulletin, array $donnees): string
    {
        $stats = $donnees['stats'];
        $visa = (new VisaComposeService)->chemin($donnees['school']);
        $celluleVisa = $visa !== null ? '<img src="'.$this->e($visa).'" style="height:20mm;">' : '';
        // Sans cachet ni signature scannés, la ligne de soulignement reste le
        // seul repère pour une signature manuscrite ; avec eux, elle ferait doublon.
        $ligneSignature = $visa === null ? '<br>_______________' : '';
        $pctReussite = $stats['evalues'] > 0 ? $this->nombre($stats['admis'] / $stats['evalues'] * 100, 1) . ' %' : '—';

        return '<table class="pied-bloc"><tr><td class="th-pied">Observations Générales<br><i>General remarks</i></td></tr>'
            . '<tr><td class="zone-obs">&nbsp;</td></tr></table>'
            . '<table class="pied-bloc" style="margin-top:2mm;">'
            . '<tr><td class="stat-lbl">Moy. Classe</td><td class="stat-val">' . $this->nombre($stats['moyenne_classe']) . '</td></tr>'
            . '<tr><td class="stat-lbl">Max / Min</td><td class="stat-val">'
            . $this->nombre($stats['premier']) . ' / ' . $this->nombre($stats['dernier']) . '</td></tr>'
            . '<tr><td class="stat-lbl">% Réussite</td><td class="stat-val">' . $pctReussite . '</td></tr></table>'
            . '<table class="no-border" style="margin-top:2mm;"><tr><td style="border:none;text-align:center;">'
            . '<b>Le Directeur</b><br><i>Head teacher</i><br>' . $celluleVisa . $ligneSignature . '</td></tr></table>';
    }
}
