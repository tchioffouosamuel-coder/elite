<?php

namespace App\Support\Pdf;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Support\ListeClasseColonnes;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * PDF de la « liste personnalisée de classe » — même rendu (en-tête,
 * bandeau, signature) que {@see ListeElevesGenerator}, mais avec des colonnes
 * choisies par l'utilisateur plutôt qu'un jeu fixe. Les lignes arrivent déjà
 * formatées ({@see \App\Services\ListeClassePersonnaliseeService::construireLignes()}) :
 * ce générateur ne fait que les mettre en page.
 */
class ListeClassePersonnaliseeGenerator
{
    use RenduDocument;

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     */
    public function build(Classe $classe, string $titreFr, string $titreEn, array $colonnes, array $lignes): string
    {
        $school = $classe->school;

        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => count($colonnes) > 6 ? 'L' : 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $school);
        $mpdf->SetTitle($titreFr);

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<hr>'
                .$this->titre($classe, $titreFr, $titreEn, count($lignes))
                .$this->tableau($colonnes, $lignes)
                .$this->signatureChef($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:'.self::ARDOISE.';color:#fff;padding:2mm;text-align:center;'
            .'font-size:3mm;font-weight:bold;margin:3mm 0}'
            .'.liste th{font-size:2.6mm}'
            .'.liste td{font-size:2.7mm;padding:1.2mm 1mm}'
            .'.liste tbody tr:nth-child(even) td{background:#f7f7f5}';
    }

    private function titre(Classe $classe, string $titreFr, string $titreEn, int $effectif): string
    {
        $anneeLibelle = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->value('libelle') ?? '—';

        return '<div style="text-align:center;line-height:1.4;">'
            .'<span class="titre">'.$this->e($titreFr).'</span><br>'
            .'<span class="titre-en">'.$this->e($titreEn).'</span>'
            .'</div>'
            .'<div class="bandeau">'
            .'Classe <i>/ Class</i> : '.$this->e($classe->nom)
            .' &nbsp;|&nbsp; Année scolaire <i>/ Academic year</i> : '.$this->e($anneeLibelle)
            .' &nbsp;|&nbsp; Effectif <i>/ Headcount</i> : '.$effectif
            .'</div>';
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     */
    private function tableau(array $colonnes, array $lignes): string
    {
        $entetes = '';
        foreach ($colonnes as $colonne) {
            [$fr, $en] = ListeClasseColonnes::libelles($colonne);
            $entetes .= '<th>'.$this->e($fr).'<br><i>'.$this->e($en).'</i></th>';
        }

        $corps = '';
        foreach ($lignes as $donnee) {
            $corps .= '<tr>';
            foreach ($colonnes as $colonne) {
                $corps .= '<td>'.$this->e((string) ($donnee[$colonne] ?? '—')).'</td>';
            }
            $corps .= '</tr>';
        }

        if ($corps === '') {
            $corps = '<tr><td colspan="'.count($colonnes).'" style="padding:6mm;">Aucun élève dans cette classe.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'.$entetes.'</tr></thead><tbody>'.$corps.'</tbody></table>';
    }
}
