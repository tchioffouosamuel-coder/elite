<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * « État de synthèse des charges et dépenses » d'un exercice, sur papier.
 *
 * Le document est fait pour être posé à côté du classeur : mêmes codes, même
 * ordre, mêmes deux colonnes, même balance en pied. Un comptable doit pouvoir
 * suivre ligne à ligne sans chercher où est passé son compte 624.
 *
 * Les comptes non mouvementés restent imprimés, avec un tiret. C'est ce qui
 * rend deux exercices superposables — une grille qui se contracte selon
 * l'activité de l'année ne se compare plus.
 *
 * La seconde page porte la lecture analytique. Elle ne remplace pas la
 * première : elle explique pourquoi la balance dit une chose et l'exploitation
 * une autre, en séparant ce qui use l'exercice de ce qui le dépasse.
 */
class EtatSyntheseGenerator
{
    use RenduDocument;

    /** @param array<string, mixed> $etat */
    public function build(School $school, array $etat): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);

        $mpdf->SetTitle('État de synthèse '.$etat['exercice']['libelle']);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
                .$this->stylesBase().$this->stylesPropres()
                .'</style></head><body>'
                .$this->enTeteEcole($school)
                .$this->titre($etat)
                .$this->colonne('Libellés des dépenses', 'Expenses', $etat['depenses'], (int) $etat['document']['total_depenses'])
                .$this->colonne('Libellés des produits', 'Income', $etat['produits'], (int) $etat['document']['total_recettes'])
                .$this->balance($etat)
                .'<pagebreak />'
                .$this->analytique($etat)
                .$this->signatureChef($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.es td{font-size:2.6mm;padding:1.1mm 1.4mm}'
            .'.es th{font-size:2.5mm}'
            .'.es .code{width:12mm;text-align:center;font-weight:bold}'
            .'.es .lib{text-align:left}'
            .'.es .num{text-align:right;width:32mm;font-weight:bold}'
            .'.es tbody tr:nth-child(even) td{background:#f7f7f5}'
            .'.es .vide td{color:#aaa;font-weight:normal}'
            .'.total td{background-color:'.self::ARDOISE.';color:#fff;font-weight:bold;font-size:2.9mm}'
            .'.section{font-weight:bold;font-size:3mm;color:'.self::ARDOISE.';margin:3mm 0 1mm;text-transform:uppercase}'
            .'.balance{margin-top:3mm}'
            .'.balance td{font-size:3.2mm;padding:2mm;font-weight:bold;border:0.6px solid '.self::ARDOISE.'}'
            .'.balance .lib{text-align:left;text-transform:uppercase}'
            .'.deficit{color:#ac3527}'
            .'.excedent{color:#1d7a35}'
            .'.note{font-size:2.4mm;color:#555;text-align:left;line-height:1.5;margin-top:2mm}'
            .'.tag{font-size:2.1mm;color:#777;font-style:italic}';
    }

    /** @param array<string, mixed> $etat */
    private function titre(array $etat): string
    {
        return '<div style="text-align:center;margin-bottom:2mm">'
            .'<span class="titre">État de synthèse des charges et dépenses '
            .$this->e($etat['exercice']['libelle']).'</span><br>'
            .'<span class="titre-en">Statement of expenses and income</span><br>'
            .'<span class="mini">Effectif de l\'exercice : <b>'.$etat['exercice']['effectif'].'</b> élèves</span>'
            .'</div>';
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    private function colonne(string $titre, string $titreEn, array $lignes, int $total): string
    {
        $corps = '';

        foreach ($lignes as $ligne) {
            $vide = (int) $ligne['montant'] === 0;

            $corps .= '<tr class="'.($vide ? 'vide' : '').'">'
                .'<td class="code">'.$this->e($ligne['code']).'</td>'
                .'<td class="lib">'.$this->e($ligne['libelle']).$this->mention($ligne).'</td>'
                .'<td class="num">'.($vide ? '—' : $this->francs((int) $ligne['montant'])).'</td>'
                .'</tr>';
        }

        return '<div class="section">'.$this->e($titre).' <span class="tag">'.$this->e($titreEn).'</span></div>'
            .'<table class="es"><thead><tr>'
            .'<th style="width:12mm">Compte</th><th>Libellé</th><th style="width:32mm">Montants</th>'
            .'</tr></thead><tbody>'.$corps.'</tbody>'
            .'<tfoot><tr class="total"><td colspan="2" class="lib">Total</td>'
            .'<td class="num">'.$this->francs($total).'</td></tr></tfoot></table>';
    }

    /**
     * Ce qui distingue une ligne des autres : un prélèvement qui se calcule,
     * un investissement qui ne devrait pas peser sur l'année, un apport qui
     * n'est pas une charge. L'imprimer évite de le réexpliquer à chaque revue.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function mention(array $ligne): string
    {
        if (($ligne['assiette'] ?? null) === 'par_eleve') {
            return ' <span class="tag">('.$this->francs((int) $ligne['montant_unitaire']).' F par élève)</span>';
        }

        return match ($ligne['nature'] ?? 'exploitation') {
            'investissement' => ' <span class="tag">(investissement — amorti sur la durée du bien)</span>',
            'capital' => ' <span class="tag">(apport de l\'exploitant — hors résultat)</span>',
            default => '',
        };
    }

    /** @param array<string, mixed> $etat */
    private function balance(array $etat): string
    {
        $balance = (int) $etat['document']['balance'];
        $apport = (int) $etat['document']['apport_fondateur'];

        $lignes = '<tr><td class="lib">Balance de fin d\'exercice</td>'
            .'<td class="num '.($balance < 0 ? 'deficit' : 'excedent').'">'.$this->francs($balance).'</td></tr>';

        if ($apport > 0) {
            $lignes .= '<tr><td class="lib">Apport personnel du fondateur et autres</td>'
                .'<td class="num">'.$this->francs($apport).'</td></tr>';
        }

        return '<table class="balance">'.$lignes.'</table>';
    }

    /** @param array<string, mixed> $etat */
    private function analytique(array $etat): string
    {
        $a = $etat['analytique'];
        $resultat = (int) $a['resultat_exploitation'];

        $ligne = fn (string $libelle, int $montant, string $classe = '') => '<tr><td class="lib">'
            .$this->e($libelle).'</td><td class="num '.$classe.'">'.$this->francs($montant).'</td></tr>';

        return '<div class="section">Lecture analytique de l\'exercice</div>'
            .'<table class="es">'
            .$ligne('Produits d\'exploitation', (int) $a['produits_exploitation'])
            .$ligne('Charges d\'exploitation', (int) $a['charges_exploitation'])
            .'<tr class="total"><td class="lib">Résultat d\'exploitation</td>'
            .'<td class="num">'.$this->francs($resultat).'</td></tr>'
            .'</table>'
            .'<table class="es" style="margin-top:4mm">'
            .$ligne('Investissement de l\'exercice (construction)', (int) $a['investissement'])
            .$ligne('Mouvements de capital (dépôts et apports)', (int) $a['capital'])
            .$ligne('Balance de fin d\'exercice (document)', (int) $etat['document']['balance'],
                (int) $etat['document']['balance'] < 0 ? 'deficit' : 'excedent')
            .'</table>'
            .'<div class="note">'
            .'La balance de fin d\'exercice additionne trois choses de natures différentes : les charges de '
            .'l\'année, la construction des bâtiments et les apports de l\'exploitant. Seule la première use '
            .'l\'exercice. La construction bâtit un actif — elle revient au résultat par son amortissement, sur '
            .'la durée du bien, et non d\'un coup l\'année du chantier. Les apports relèvent du haut de bilan : '
            .'ils ne sont ni une charge, ni un produit.'
            .'</div>';
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
