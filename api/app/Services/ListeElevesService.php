<?php

namespace App\Services;

use App\Models\Classe;
use App\Support\Word\EnTeteWord;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;

class ListeElevesService extends BaseService
{
    /** Mêmes couleurs que les documents PDF (cf. Concerns\RenduDocument). */
    private const ACCENT = '39B54A';

    private const ARDOISE = '292F36';

    /**
     * Génère la liste des élèves d'une classe en .docx et retourne le chemin
     * du fichier temporaire (à supprimer après envoi par le contrôleur).
     */
    public function genererWord(Classe $classe, Collection $eleves): string
    {
        $school = $classe->school;

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        // Marges « étroites » de Word (1,27 cm / 0,5 po de chaque côté) : la
        // liste gagne en largeur utile sans empiéter sur la zone imprimable.
        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
            'marginRight' => 720,
        ]);

        // Même en-tête que la version PDF (cf. ListeElevesGenerator) : mentions
        // officielles FR, logo, mentions EN. Sans lui, le document Word ne
        // valait pas pièce administrative.
        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $section->addText('LISTE DES ÉLÈVES', ['bold' => true, 'size' => 14, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText('STUDENT LIST', ['italic' => true, 'size' => 11, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 120]);

        $this->ajouterBandeau($section, $classe, $eleves->count());
        $this->ajouterEffectifs($section, $eleves);

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'alignment' => JcTable::CENTER,
        ]);

        // Somme = largeur imprimable avec les marges étroites ci-dessus
        // (page A4 11906 twips − 2×720 de marge = 10466) : le tableau occupe
        // toute la largeur disponible sans la dépasser.
        $largeurs = [500, 1500, 3800, 700, 2800, 1166];
        // Intitulés bilingues comme sur le PDF : FR au-dessus, EN en italique.
        $entetes = [
            ['N°', 'No.'],
            ['Matricule', 'ID number'],
            ['Nom et prénoms', 'Full name'],
            ['Sexe', 'Sex'],
            ['Tuteur / Parent', 'Guardian'],
            ['Téléphone', 'Phone'],
        ];

        // `tblHeader` : la ligne d'en-tête se répète en haut de chaque page.
        // Une classe de 60 élèves déborde, et la deuxième page arrivait sans
        // aucun intitulé de colonne.
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($entetes as $i => [$fr, $en]) {
            $cellule = $table->addCell($largeurs[$i], ['bgColor' => self::ACCENT]);
            $ligne = $cellule->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
            $ligne->addText($fr, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addTextBreak();
            $ligne->addText($en, ['italic' => true, 'color' => 'FFFFFF', 'size' => 7]);
        }

        $rang = 1;
        foreach ($eleves as $eleve) {
            $tuteur = $eleve->tuteurs->first();

            $table->addRow();
            $table->addCell($largeurs[0])->addText((string) $rang, ['size' => 9], ['alignment' => JcTable::CENTER]);
            $table->addCell($largeurs[1])->addText($eleve->matricule ?: '—', ['size' => 9], ['alignment' => JcTable::CENTER]);
            $table->addCell($largeurs[2])->addText($eleve->nom_complet, ['bold' => true, 'size' => 9]);
            $table->addCell($largeurs[3])->addText($eleve->sexe, ['size' => 9], ['alignment' => JcTable::CENTER]);
            $table->addCell($largeurs[4])->addText($tuteur?->nom_complet ?: '—', ['size' => 9]);
            $table->addCell($largeurs[5])->addText($tuteur?->telephone ?: '—', ['size' => 9], ['alignment' => JcTable::CENTER]);
            $rang++;
        }

        if ($eleves->isEmpty()) {
            $table->addRow();
            $table->addCell(array_sum($largeurs), ['gridSpan' => 6])
                ->addText('Aucun élève dans cette classe.', ['size' => 9, 'italic' => true], ['alignment' => JcTable::CENTER]);
        }

        $this->ajouterSignature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/liste-eleves-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** Bandeau ardoise classe / année / effectif, comme sur le PDF. */
    private function ajouterBandeau(Section $section, Classe $classe, int $effectif): void
    {
        $bandeau = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'cellMargin' => 60, 'alignment' => JcTable::CENTER]);
        $bandeau->addRow();
        $ligne = $bandeau->addCell(null, ['bgColor' => self::ARDOISE])
            ->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);

        $mentions = [
            ['Classe ', '/ Class', ' : '.$classe->nom],
            ['Année scolaire ', '/ Academic year', ' : '.($classe->anneeScolaire?->libelle ?? '—')],
            ['Effectif ', '/ Headcount', ' : '.$effectif],
        ];

        foreach ($mentions as $i => [$fr, $en, $valeur]) {
            if ($i > 0) {
                $ligne->addText('   |   ', ['color' => 'FFFFFF', 'size' => 9]);
            }

            $ligne->addText($fr, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addText($en, ['italic' => true, 'color' => 'FFFFFF', 'size' => 8]);
            $ligne->addText($valeur, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }

    /**
     * Petit tableau des effectifs (filles / garçons / total), calé à gauche
     * sur 30 % de la largeur de page — avant les en-têtes du tableau
     * nominatif, comme une synthèse rapide avant le détail.
     */
    private function ajouterEffectifs(Section $section, Collection $eleves): void
    {
        $filles = $eleves->where('sexe', 'F')->count();
        $garcons = $eleves->where('sexe', 'M')->count();

        $table = $section->addTable([
            'width' => 30 * 50,
            'unit' => 'pct',
            'alignment' => JcTable::START,
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
        ]);

        $colonnes = [
            ['Filles', 'Girls', $filles],
            ['Garçons', 'Boys', $garcons],
            ['Total', 'Total', $eleves->count()],
        ];

        $table->addRow();
        foreach ($colonnes as [$fr, $en, $valeur]) {
            $cellule = $table->addCell(null, ['bgColor' => self::ARDOISE]);
            $ligne = $cellule->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
            $ligne->addText($fr, ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
            $ligne->addTextBreak();
            $ligne->addText($en, ['italic' => true, 'color' => 'FFFFFF', 'size' => 6]);
        }

        $table->addRow();
        foreach ($colonnes as [$fr, $en, $valeur]) {
            $table->addCell(null)->addText((string) $valeur, ['bold' => true, 'size' => 11], ['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }

    /** Lieu et date à gauche, bloc de signature du chef d'établissement à droite. */
    private function ajouterSignature(Section $section, $school): void
    {
        $section->addTextBreak(1);

        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $lieu = $ville !== '' ? "Fait à {$ville}, le " : 'Fait le ';

        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'alignment' => JcTable::CENTER]);
        $table->addRow();

        $table->addCell(5000, ['valign' => 'top'])
            ->addText($lieu.now()->format('d/m/Y'), ['size' => 9], ['spaceAfter' => 0]);

        $droite = $table->addCell(5000, ['valign' => 'top']);
        $titre = $droite->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
        $titre->addText("Le Chef d'Établissement", ['bold' => true, 'size' => 9]);
        $titre->addTextBreak();
        $titre->addText('The Principal', ['italic' => true, 'size' => 8]);

        $this->ajouterVisa($droite, $school);
        $droite->addText(
            'Signature et cachet',
            ['size' => 8],
            ['alignment' => JcTable::CENTER, 'spaceAfter' => 0, 'borderTopSize' => 4, 'borderTopColor' => '000000'],
        );
    }

    /**
     * Cachet et signature composés en une image (la signature traverse le
     * cachet), s'ils ont été chargés ; sinon un simple blanc à signer.
     */
    private function ajouterVisa($cellule, $school): void
    {
        $visa = (new VisaComposeService)->chemin($school);

        if ($visa === null) {
            $cellule->addTextBreak(3);

            return;
        }

        $cellule->addImage($visa, ['height' => 46, 'alignment' => JcTable::CENTER]);
    }
}
