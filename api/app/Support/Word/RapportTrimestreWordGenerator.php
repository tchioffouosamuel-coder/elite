<?php

namespace App\Support\Word;

use App\Models\EquipementMobilier;
use App\Models\School;
use App\Services\VisaComposeService;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Rapport de fin de trimestre, canevas MINEDUB — version .docx fidèle au
 * gabarit papier, sur le même principe que `RapportRentreeWordGenerator` :
 * en-tête bilingue, bloc titre encadré, pied de page administratif, tableaux
 * à bordures noires sans fond coloré.
 *
 * Consomme le tableau `$d` produit par `RapportTrimestreService::generer()`.
 * Une seule rubrique reste hors de portée du système — l'assiduité des
 * enseignants (pas de pointage journalier du personnel) — imprimée comme
 * une rubrique « à compléter manuellement ».
 */
class RapportTrimestreWordGenerator
{
    private const BRUN_FOOTER = 'B5651D';

    private const LIBELLES_TEXTE = [
        'introduction' => 'Introduction',
        'observations_structure' => 'Observations',
        'observations_eleves' => 'Observations',
        'observations_personnel' => 'Observations',
        'difficultes_rencontrees' => 'Difficultés rencontrées',
        'conclusion_generale' => 'Conclusion générale',
    ];

    private const MATERIAUX = ['dur' => 'Dur', 'semi_dur' => 'Semi-dur', 'provisoire' => 'Matériaux provisoires'];

    private const ETATS = ['bon' => 'Bon', 'assez_bon' => 'Assez-bon', 'mauvais' => 'Mauvais'];

    private const TYPES_INFRA = [
        'wc' => 'WC (latrines)', 'cloture' => 'Clôture', 'point_eau' => "Point d'eau",
        'electricite' => 'Électricité', 'aire_jeu' => 'Aire de jeu', 'logement_maitre' => 'Logement maître', 'autre' => 'Autre',
    ];

    private const CATEGORIES_MINORITE = [
        'camerounais' => 'Camerounais',
        'deplaces_internes' => 'Déplacés internes',
        'refugies' => 'Réfugiés',
        'bororo' => 'Bororo',
        'baka' => 'Baka',
    ];

    public function build(array $d): string
    {
        /** @var School $school */
        $school = $d['school'];

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 1000, 'marginRight' => 1000,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);
        $this->ajouterPiedDePage($section, $school);

        $this->blocTitre($section, $d);
        $this->sectionIntroduction($section, $d);
        $this->sectionStructure($section, $d);
        $this->sectionEleves($section, $d);
        $this->sectionPedagogie($section, $d);
        $this->sectionPersonnel($section, $d);
        $this->sectionDifficultes($section, $d);
        $this->sectionConclusion($section, $d);

        $this->signature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/rapport-trimestre-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    // -- Structure --------------------------------------------------

    private function blocTitre(Section $section, array $d): void
    {
        $school = $d['school'];
        $trimestre = $d['trimestre'];

        $table = $section->addTable(['borderSize' => 10, 'borderColor' => '000000', 'cellMargin' => 200, 'alignment' => JcTable::CENTER, 'width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();
        $cellule = $table->addCell(null, ['valign' => 'center']);

        $cellule->addText('RAPPORT DE FIN DE '.mb_strtoupper((string) $trimestre->libelle), ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $cellule->addText('DE '.mb_strtoupper((string) $school->name), ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $cellule->addText('ANNÉE SCOLAIRE '.($trimestre->anneeScolaire->libelle ?? ''), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $section->addTextBreak(1);

        $identite = $d['identite'];
        $presente = $section->addTextRun(['alignment' => Jc::END, 'spaceAfter' => 60]);
        $presente->addText('Présenté par : ', ['italic' => true]);
        $presente->addText($identite['directeur_nom'] ?? '……………………………………', ['italic' => true, 'bold' => true]);

        $section->addTextBreak(1);
    }

    private function sectionIntroduction(Section $section, array $d): void
    {
        $this->titreRomain($section, 'I. INTRODUCTION');
        $section->addText($d['textes']['introduction'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionStructure(Section $section, array $d): void
    {
        $this->titreRomain($section, 'II. PRÉSENTATION DE LA STRUCTURE');
        $this->titreSous($section, 'a. Équipements et mobilier');

        $infra = $d['infrastructures'];

        $grille = function (array $g, string $titre) use ($section) {
            $lignes = [];
            foreach (self::MATERIAUX as $mat => $labelMat) {
                foreach (self::ETATS as $etat => $labelEtat) {
                    $q = $g[$mat][$etat] ?? 0;
                    if ($q > 0) {
                        $lignes[] = [$labelMat, $labelEtat, $q];
                    }
                }
            }
            $section->addText($titre, ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
            $this->tableau($section, ['Matériau', 'État', 'Quantité'], $lignes, 'Aucune infrastructure de ce type.');
        };

        $grille($infra['salles_classe'], 'Salles de classe');
        $grille($infra['bloc_administratif'], 'Bloc administratif');

        $autres = collect($infra['autres'])->filter(fn ($q) => $q > 0)
            ->map(fn ($q, $type) => [self::TYPES_INFRA[$type] ?? $type, $q])->values()->all();
        $section->addText('Locaux (clôture, latrines…)', ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
        $this->tableau($section, ['Nature', 'Quantité'], $autres, 'Aucun.');

        $equipements = $infra['equipements']->map(fn (EquipementMobilier $e) => [$e->nature, $e->quantite, $e->besoin_quantite ?? '—'])->all();
        $section->addText('Mobilier', ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
        $this->tableau($section, ['Désignation', 'Quantité', 'Besoins'], $equipements);

        $this->titreSous($section, 'b. Observations');
        $section->addText($d['textes']['observations_structure'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionEleves(Section $section, array $d): void
    {
        $this->titreRomain($section, 'III. LES ÉLÈVES');

        $this->titreSous($section, 'a. Effectifs désagrégés en fin de trimestre');
        $lignes = [];
        $tG = 0;
        $tF = 0;
        foreach ($d['effectifs_par_classe'] as $l) {
            $lignes[] = [$l['classe']['nom'], $l['garcons']['total'], $l['filles']['total'], $l['total']['total']];
            $tG += $l['garcons']['total'];
            $tF += $l['filles']['total'];
        }
        if ($lignes !== []) {
            $lignes[] = ['TOTAL', $tG, $tF, $tG + $tF];
        }
        $this->tableau($section, ['Classes', 'G', 'F', 'T'], $lignes);

        $this->titreSous($section, 'b. Effectifs des élèves camerounais et non camerounais en fin de trimestre');
        $tCam = ['garcons' => 0, 'filles' => 0];
        $tNonCam = ['garcons' => 0, 'filles' => 0];
        foreach ($d['effectifs_par_classe'] as $l) {
            $tCam['garcons'] += $l['garcons']['camerounais'];
            $tCam['filles'] += $l['filles']['camerounais'];
            $tNonCam['garcons'] += $l['garcons']['non_camerounais'];
            $tNonCam['filles'] += $l['filles']['non_camerounais'];
        }
        $this->tableau($section, ['Nationalité', 'G', 'F', 'T'], [
            ['Camerounais', $tCam['garcons'], $tCam['filles'], $tCam['garcons'] + $tCam['filles']],
            ['Non camerounais (réfugiés)', $tNonCam['garcons'], $tNonCam['filles'], $tNonCam['garcons'] + $tNonCam['filles']],
            ['TOTAL', $tG, $tF, $tG + $tF],
        ]);

        $this->titreSous($section, 'c. Effectifs des minorités en fin de trimestre');
        $m = $d['minorites'];
        $this->tableau($section, ['Minorité', 'G', 'F', 'T'], [
            ['Bororo', $m['bororo']['garcons'], $m['bororo']['filles'], $m['bororo']['total']],
            ['Baka', $m['baka']['garcons'], $m['baka']['filles'], $m['baka']['total']],
            ['Déplacés internes', $m['deplaces_internes']['garcons'], $m['deplaces_internes']['filles'], $m['deplaces_internes']['total']],
            ['TOTAL', $m['total']['garcons'], $m['total']['filles'], $m['total']['total']],
        ]);

        $this->titreSous($section, 'd. Taux de fréquentation trimestriel par cours et par sexe');
        $lignes = array_map(fn (array $l) => [
            $l['classe'], $l['garcons']['taux'].' %', $l['filles']['taux'].' %', $l['total']['taux'].' %',
        ], $d['frequentation_par_classe']);
        $this->tableau($section, ['Cours', 'G', 'F', 'T'], $lignes, 'Aucune séance enregistrée sur ce trimestre.');

        $this->titreSous($section, 'e. Taux de fréquentation des minorités');
        foreach (self::CATEGORIES_MINORITE as $cle => $label) {
            $lignes = array_map(fn (array $l) => [
                $l['classe'], $l['categories'][$cle]['garcons']['taux'].' %',
                $l['categories'][$cle]['filles']['taux'].' %', $l['categories'][$cle]['total']['taux'].' %',
            ], $d['frequentation_minorites_par_classe']);

            $section->addText($label, ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
            $this->tableau($section, ['Cours', 'G', 'F', 'T'], $lignes, 'Aucun élève de cette catégorie.');
        }

        $this->titreSous($section, 'f. Observations');
        $section->addText($d['textes']['observations_eleves'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionPedagogie(Section $section, array $d): void
    {
        $this->titreRomain($section, 'IV. LA PÉDAGOGIE');

        $this->titreSous($section, 'a. Couverture des programmes');
        $lignes = array_map(fn (array $c) => [
            $c['classe'], $c['lecons_trimestre'], $c['traitees_trimestre'], $c['taux_trimestre'].' %', $c['taux_annee'].' %',
        ], $d['couverture_par_classe']);
        $this->tableau($section, ['Classe', 'Prévu ce trimestre', 'Couvert ce trimestre', '% trimestre', '% annuel'], $lignes);

        $this->titreSous($section, 'b. Promotion interne en fin de trimestre');
        $lignes = array_map(fn (array $p) => [$p['classe'], $p['effectif'], $p['admis'], $p['taux_promotion'].' %'], $d['promotion_par_classe']);
        $this->tableau($section, ['Classe', 'Effectif', 'Admis (≥ seuil)', 'Taux de promotion'], $lignes);

        $this->titreSous($section, 'c. Résultats internes');
        $lignes = array_map(fn (array $p) => [
            $p['classe'], $p['moyenne_classe'] ?? '—', $p['moyenne_plus_forte'] ?? '—', $p['moyenne_plus_faible'] ?? '—',
        ], $d['promotion_par_classe']);
        $this->tableau($section, ['Classe', 'Moyenne de classe', 'Plus forte moyenne', 'Plus faible moyenne'], $lignes);
    }

    private function sectionPersonnel(Section $section, array $d): void
    {
        $this->titreRomain($section, 'V. PERSONNEL');

        $this->titreSous($section, "a. Tableau d'assiduité des enseignants");
        $this->noteManuelle($section, "Non suivi par le système (pas de pointage journalier du personnel) : à compléter manuellement.");

        $this->titreSous($section, 'b. Observations');
        $section->addText($d['textes']['observations_personnel'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionDifficultes(Section $section, array $d): void
    {
        $this->titreRomain($section, 'VI. DIFFICULTÉS RENCONTRÉES');
        $section->addText($d['textes']['difficultes_rencontrees'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionConclusion(Section $section, array $d): void
    {
        $section->addTextRun(['spaceAfter' => 120])->addText('CONCLUSION GÉNÉRALE :', ['bold' => true, 'underline' => 'single']);
        $section->addText($d['textes']['conclusion_generale'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 0]);
    }

    // -- Signature ----------------------------------------------------

    private function signature(Section $section, School $school): void
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        $section->addTextBreak(1);
        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Fait à '.($ville !== '' ? $ville : '…………').', le '.now()->format('d/m/Y'));

        $titre = $section->addTextRun(['alignment' => Jc::END, 'spaceAfter' => 0]);
        $titre->addText('Le/La Chef d\'établissement', ['bold' => true]);

        $visa = (new VisaComposeService)->chemin($school);
        if ($visa !== null) {
            $section->addImage($visa, ['height' => 50, 'alignment' => Jc::END]);
        } else {
            $section->addTextBreak(3);
        }
    }

    // -- Pied de page ---------------------------------------------------

    private function ajouterPiedDePage(Section $section, School $school): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();

        $nomEspace = implode(' ', preg_split('//u', mb_strtoupper((string) $school->name), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $gauche = $table->addCell(8000, ['bgColor' => self::BRUN_FOOTER, 'valign' => 'center']);
        $gauche->addText($nomEspace, ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);

        $droite = $table->addCell(1500, ['bgColor' => self::BRUN_FOOTER, 'valign' => 'center']);
        $droite->addPreserveText('{PAGE}', ['bold' => true, 'color' => 'FFFFFF', 'size' => 9], ['alignment' => Jc::END]);
    }

    // -- Primitives -----------------------------------------------------

    private function titreRomain(Section $section, string $texte): void
    {
        $section->addText($texte, ['bold' => true, 'underline' => 'single', 'size' => 11], ['spaceBefore' => 200, 'spaceAfter' => 100]);
    }

    private function titreSous(Section $section, string $texte): void
    {
        $section->addText($texte, ['bold' => true, 'underline' => 'single', 'size' => 10], ['spaceBefore' => 160, 'spaceAfter' => 80]);
    }

    private function noteManuelle(Section $section, string $texte): void
    {
        $section->addText($texte, ['italic' => true, 'size' => 8, 'color' => '777777'], ['spaceAfter' => 120]);
    }

    /** Tableau générique du canevas : bordures noires, en-tête gras sans fond coloré. */
    private function tableau(Section $section, array $entetes, array $lignes, ?string $vide = null): void
    {
        if (count($lignes) === 0) {
            $this->noteManuelle($section, $vide ?? 'Aucune donnée enregistrée.');

            return;
        }

        $table = $section->addTable([
            'borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60,
            'width' => 100 * 50, 'unit' => 'pct',
        ]);

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($entetes as $entete) {
            $table->addCell(null)->addText((string) $entete, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }

        foreach ($lignes as $ligne) {
            $table->addRow();
            foreach ($ligne as $cellule) {
                $table->addCell(null)->addText(
                    $cellule !== null && $cellule !== '' ? (string) $cellule : '—',
                    ['size' => 9],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
                );
            }
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }
}
