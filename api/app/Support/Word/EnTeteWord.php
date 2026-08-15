<?php

namespace App\Support\Word;

use App\Models\School;
use App\Support\Pdf\EnTeteHtml;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * En-tête d'établissement pour les documents .docx — équivalent Word de
 * `RenduDocument::enTeteEcole()`, qui rend le même bandeau en mPDF.
 *
 * Les documents Word se contentaient du nom de l'école et de son adresse : les
 * établissements ayant saisi leur en-tête officiel (République du Cameroun,
 * ministère, région, département) et chargé leur logo les voyaient sur les PDF
 * et pas sur les listes Word, qui ne passaient donc pas pour des documents
 * administratifs. Même structure ici : mentions FR, logo, mentions EN.
 *
 * Le HTML de l'éditeur est converti à la main plutôt que confié au parseur de
 * PhpWord : celui-ci aligne à gauche tout paragraphe sans style explicite,
 * alors que les trois colonnes de l'en-tête sont centrées.
 */
class EnTeteWord
{
    private const ACCENT = '39B54A';

    private const GRIS_FILET = 'BDC3C7';

    /** Largeurs en twips (twentieths of a point) : 40 % / 20 % / 40 % d'une page A4 utile. */
    private const LARGEURS = [4000, 2000, 4000];

    public static function ajouter(Section $section, School $school): void
    {
        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'cellMargin' => 40]);
        $table->addRow();

        // Le filet sous l'en-tête reprend le <hr> des PDF. Porté par la bordure
        // basse des cellules : PhpWord n'a pas d'équivalent fiable du trait
        // horizontal, et une bordure de cellule se rend partout de la même façon.
        $bordure = ['borderBottomSize' => 6, 'borderBottomColor' => self::GRIS_FILET, 'valign' => 'top'];

        self::colonneTexte($table->addCell(self::LARGEURS[0], $bordure), $school->header_fr, $school->name);
        self::colonneLogo($table->addCell(self::LARGEURS[1], $bordure), $school);
        self::colonneTexte($table->addCell(self::LARGEURS[2], $bordure), $school->header_en, $school->name);

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }

    /** Mentions officielles d'un côté de l'en-tête ; repli sur le nom de l'école. */
    private static function colonneTexte(mixed $cellule, ?string $html, string $repli): void
    {
        $paragraphes = self::paragraphes(EnTeteHtml::render($html));

        if ($paragraphes === []) {
            $paragraphes = [[['texte' => $repli, 'bold' => true, 'italic' => false, 'underline' => false]]];
        }

        foreach ($paragraphes as $runs) {
            $ligne = $cellule->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.05]);

            foreach ($runs as $run) {
                $ligne->addText($run['texte'], [
                    'size' => 8,
                    'bold' => $run['bold'],
                    'italic' => $run['italic'],
                    'underline' => $run['underline'] ? 'single' : 'none',
                ]);
            }
        }
    }

    /** Logo de l'établissement, ou monogramme quand il n'a pas encore été chargé. */
    private static function colonneLogo(mixed $cellule, School $school): void
    {
        $logo = self::cheminImage($school->logo_path);

        if ($logo !== null) {
            // 105 pt ≈ les 140 px de la version PDF.
            $cellule->addImage($logo, ['width' => 105, 'height' => 105, 'alignment' => Jc::CENTER]);

            return;
        }

        $cellule->addText(
            self::monogramme($school),
            ['size' => 20, 'bold' => true, 'color' => self::ACCENT],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );
    }

    private static function cheminImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $complet = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($complet) ? $complet : null;
    }

    /** Mêmes initiales que le monogramme des PDF : trois lettres au plus. */
    private static function monogramme(School $school): string
    {
        $mots = preg_split('/\s+/', trim((string) $school->name)) ?: [];
        $lettres = array_map(static fn (string $mot) => mb_substr($mot, 0, 1), array_slice($mots, 0, 3));

        return mb_strtoupper(implode('', $lettres));
    }

    /**
     * Découpe le HTML de l'en-tête en lignes, chacune décrite comme une suite
     * de fragments portant leur mise en forme. `<br>` et les balises de bloc
     * ouvrent une ligne ; gras, italique et souligné s'héritent en cascade.
     *
     * @return list<list<array{texte: string, bold: bool, italic: bool, underline: bool}>>
     */
    private static function paragraphes(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $racine = $doc->getElementsByTagName('div')->item(0);

        if (! $racine) {
            return [];
        }

        $lignes = [[]];
        self::parcourir($racine, ['bold' => false, 'italic' => false, 'underline' => false], $lignes);

        // Les balises de bloc ouvrent une ligne à l'entrée comme à la sortie :
        // sans ce filtrage, un en-tête en <p> se retrouverait aéré de lignes vides.
        return array_values(array_filter($lignes, static fn (array $runs) => $runs !== []));
    }

    /**
     * @param  array{bold: bool, italic: bool, underline: bool}  $format
     * @param  list<list<array{texte: string, bold: bool, italic: bool, underline: bool}>>  $lignes
     */
    private static function parcourir(DOMNode $noeud, array $format, array &$lignes): void
    {
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                // Le HTML de l'éditeur est indenté : sans normalisation, les
                // retours à la ligne du source deviendraient des espaces parasites.
                $texte = preg_replace('/\s+/u', ' ', $enfant->textContent) ?? '';

                if (trim($texte) !== '') {
                    $lignes[array_key_last($lignes)][] = [...$format, 'texte' => $texte];
                }

                continue;
            }

            if (! $enfant instanceof DOMElement) {
                continue;
            }

            $balise = strtolower($enfant->nodeName);

            if ($balise === 'br') {
                $lignes[] = [];

                continue;
            }

            $bloc = in_array($balise, ['p', 'div', 'li', 'ul', 'ol'], true);

            if ($bloc && $lignes[array_key_last($lignes)] !== []) {
                $lignes[] = [];
            }

            self::parcourir($enfant, [
                'bold' => $format['bold'] || in_array($balise, ['strong', 'b'], true),
                'italic' => $format['italic'] || in_array($balise, ['em', 'i'], true),
                'underline' => $format['underline'] || $balise === 'u',
            ], $lignes);

            if ($bloc && $lignes[array_key_last($lignes)] !== []) {
                $lignes[] = [];
            }
        }
    }
}
