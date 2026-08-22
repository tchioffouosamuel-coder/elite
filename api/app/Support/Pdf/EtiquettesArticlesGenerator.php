<?php

namespace App\Support\Pdf;

use App\Models\InventaireArticle;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Planche d'étiquettes code-barres à découper et coller sur les articles.
 *
 * Le code-barres est tracé par mPDF lui-même (`<barcode type="EAN13">`) : aucune
 * bibliothèque de génération d'images à installer, et le trait reste vectoriel
 * — un code rasterisé se dégrade à l'impression et cesse d'être lu par la
 * douchette, ce qui est précisément ce qu'on ne peut pas se permettre ici.
 *
 * La grille est calée sur des planches A4 courantes de 3 × 8 : si l'école
 * imprime sur du papier ordinaire, elle découpe ; si elle achète des planches
 * autocollantes, les repères tombent juste.
 */
class EtiquettesArticlesGenerator
{
    use RenduDocument;

    private const COLONNES = 3;

    /** Hauteur d'une étiquette, en millimètres. */
    private const HAUTEUR = 33;

    /**
     * @param  Collection<int, InventaireArticle>  $articles
     * @param  array<int, int>  $exemplaires  article_id => nombre d'étiquettes à tirer
     */
    public function build(Collection $articles, ?School $school, array $exemplaires = []): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 8,
        ], $school);
        $mpdf->SetTitle('Étiquettes code-barres');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
            .$this->planche($articles, $exemplaires)
            .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.grille{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:2mm}'
            .'.grille td{border:0.3mm dashed #9aa3ad;height:'.self::HAUTEUR.'mm;'
            .'text-align:center;vertical-align:middle;padding:1.5mm}'
            .'.grille td.vide{border:none}'
            .'.nom{font-weight:bold;font-size:2.7mm;color:#111}'
            .'.prix{font-size:3.2mm;font-weight:bold;color:'.self::ARDOISE.'}'
            .'.ecole{font-size:2mm;color:#7a828c;text-transform:uppercase}'
            .'.sans-code{font-size:2.4mm;color:#ac3527}';
    }

    /**
     * @param  Collection<int, InventaireArticle>  $articles
     * @param  array<int, int>  $exemplaires
     */
    private function planche(Collection $articles, array $exemplaires): string
    {
        $cellules = [];

        foreach ($articles as $article) {
            $tirage = max(1, (int) ($exemplaires[$article->id] ?? 1));

            for ($i = 0; $i < $tirage; $i++) {
                $cellules[] = $this->etiquette($article);
            }
        }

        if ($cellules === []) {
            return '<p style="text-align:center">Aucun article à étiqueter.</p>';
        }

        // Complète la dernière ligne : une cellule manquante ferait s'étirer
        // les précédentes sur toute la largeur.
        while (count($cellules) % self::COLONNES !== 0) {
            $cellules[] = null;
        }

        $html = '<table class="grille">';

        foreach (array_chunk($cellules, self::COLONNES) as $ligne) {
            $html .= '<tr>';

            foreach ($ligne as $cellule) {
                $html .= $cellule === null ? '<td class="vide"></td>' : '<td>'.$cellule.'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    private function etiquette(InventaireArticle $article): string
    {
        // Un article partagé n'a pas d'école : le nommer, plutôt que de laisser
        // la ligne vide sur l'étiquette collée à l'article.
        $ecole = $article->school?->name ?? 'Toutes les écoles';

        $html = '<span class="ecole">'.$this->e($ecole).'</span><br>';
        $html .= '<span class="nom">'.$this->e($article->nom).'</span><br>';

        if ($article->code_barre === null) {
            // Ne devrait pas arriver : le contrôleur attribue le code avant
            // d'imprimer. On le signale plutôt que de rendre une case muette.
            return $html.'<span class="sans-code">Code-barres non attribué</span>';
        }

        $html .= '<barcode code="'.$this->e($article->code_barre).'" type="EAN13" size="0.85" height="0.85" />';

        if ($article->prix_vente !== null) {
            $html .= '<br><span class="prix">'.number_format((float) $article->prix_vente, 0, ',', ' ').' F</span>';
        }

        return $html;
    }
}
