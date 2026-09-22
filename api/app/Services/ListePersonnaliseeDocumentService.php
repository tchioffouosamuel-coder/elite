<?php

namespace App\Services;

use App\Models\School;
use App\Support\Pdf\ListePersonnaliseeGenerator;
use App\Support\Word\EnTeteWord;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;

/** Rendu partagé PDF/Word des listes personnalisées hors classe. */
class ListePersonnaliseeDocumentService
{
    private const ACCENT = '39B54A';

    private const ARDOISE = '292F36';

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, array{fr: string, en: string}>  $definitions
     * @param  array<string, string>  $meta
     */
    public function genererPdf(School $school, string $titreFr, string $titreEn, array $colonnes, array $lignes, array $definitions, array $meta = []): string
    {
        return (new ListePersonnaliseeGenerator)->build($school, $titreFr, $titreEn, $colonnes, $lignes, $definitions, $meta);
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, array{fr: string, en: string}>  $definitions
     * @param  array<string, string>  $meta
     */
    public function genererWord(School $school, string $titreFr, string $titreEn, array $colonnes, array $lignes, array $definitions, array $meta = []): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
            'marginRight' => 720,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $section->addText(mb_strtoupper($titreFr), ['bold' => true, 'size' => 14, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText($titreEn, ['italic' => true, 'size' => 11, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 120]);

        $this->ajouterBandeau($section, count($lignes), $meta);
        $this->ajouterTableau($section, $colonnes, $lignes, $definitions);
        $this->ajouterSignature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/liste-personnalisee-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** @param array<string, string> $meta */
    private function ajouterBandeau(Section $section, int $effectif, array $meta): void
    {
        $mentions = array_merge($meta, ['Effectif / Headcount' => (string) $effectif]);
        $bandeau = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'cellMargin' => 60, 'alignment' => JcTable::CENTER]);
        $bandeau->addRow();
        $ligne = $bandeau->addCell(null, ['bgColor' => self::ARDOISE])
            ->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);

        $index = 0;
        foreach ($mentions as $cle => $valeur) {
            if ($index > 0) {
                $ligne->addText('   |   ', ['color' => 'FFFFFF', 'size' => 9]);
            }
            $ligne->addText($cle.' : ', ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addText($valeur, ['color' => 'FFFFFF', 'size' => 9]);
            $index++;
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, array{fr: string, en: string}>  $definitions
     */
    private function ajouterTableau(Section $section, array $colonnes, array $lignes, array $definitions): void
    {
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'alignment' => JcTable::CENTER,
        ]);
        $largeur = intdiv(10466, max(count($colonnes), 1));

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($colonnes as $colonne) {
            $definition = $definitions[$colonne] ?? ['fr' => $colonne, 'en' => $colonne];
            $cellule = $table->addCell($largeur, ['bgColor' => self::ACCENT]);
            $ligne = $cellule->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
            $ligne->addText($definition['fr'], ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addTextBreak();
            $ligne->addText($definition['en'], ['italic' => true, 'color' => 'FFFFFF', 'size' => 7]);
        }

        foreach ($lignes as $donnee) {
            $table->addRow();
            foreach ($colonnes as $colonne) {
                $table->addCell($largeur)->addText((string) ($donnee[$colonne] ?? '—'), ['size' => 9], ['alignment' => JcTable::CENTER]);
            }
        }

        if ($lignes === []) {
            $table->addRow();
            $table->addCell($largeur * count($colonnes), ['gridSpan' => count($colonnes)])
                ->addText('Aucune donnée.', ['size' => 9, 'italic' => true], ['alignment' => JcTable::CENTER]);
        }
    }

    private function ajouterSignature(Section $section, School $school): void
    {
        $section->addTextBreak(1);

        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $lieu = $ville !== '' ? "Fait à {$ville}, le " : 'Fait le ';

        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'alignment' => JcTable::CENTER]);
        $table->addRow();
        $table->addCell(5000, ['valign' => 'top'])->addText($lieu.now()->format('d/m/Y'), ['size' => 9], ['spaceAfter' => 0]);

        $droite = $table->addCell(5000, ['valign' => 'top']);
        $titre = $droite->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
        $titre->addText("Le Chef d'Établissement", ['bold' => true, 'size' => 9]);
        $titre->addTextBreak();
        $titre->addText('The Principal', ['italic' => true, 'size' => 8]);
        $droite->addTextBreak(3);
        $droite->addText('Signature et cachet', ['size' => 8], ['alignment' => JcTable::CENTER, 'spaceAfter' => 0, 'borderTopSize' => 4, 'borderTopColor' => '000000']);
    }
}
