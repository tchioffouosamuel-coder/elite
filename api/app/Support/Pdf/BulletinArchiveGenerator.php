<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Bulletin d'une année archivée, reconstruit depuis `archives_classe_annee.notes_json`
 * (un instantané de scalaires, jamais recalculé depuis les tables vivantes)
 * — délibérément un générateur distinct de {@see BulletinGenerator} : un
 * bulletin archivé doit rester identique à ce qu'il était au moment du gel,
 * même si un coefficient ou une affectation a changé depuis côté classe
 * vivante (gabarit permanent, réutilisé chaque année).
 *
 * @param array{
 *   eleve: array{nom_complet:string, matricule:?string, sexe:?string},
 *   classe: array{nom:string},
 *   annee: array{libelle:string},
 *   school: School,
 *   trimestres: list<array{libelle:string, matieres: list<array{matiere:string, moyenne:?float, rang:?int, coefficient:?float}>, moyenne_generale:?float, rang_general:?int}>,
 *   moyenne_annuelle: ?float,
 *   rang_annuel: ?int,
 * } $donnees
 */
class BulletinArchiveGenerator
{
    use RenduDocument;

    public function build(array $donnees): string
    {
        /** @var School $school */
        $school = $donnees['school'];

        $mpdf = MpdfFactory::make(['orientation' => 'P', 'margin_top' => 10, 'margin_bottom' => 10], $school);
        $mpdf->SetTitle('Bulletin archivé — '.$donnees['eleve']['nom_complet'].' — '.$donnees['annee']['libelle']);
        MpdfFactory::appliquerFiligrane($mpdf, $school, largeurMm: 130);

        $mpdf->WriteHTML($this->html($donnees));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function html(array $donnees): string
    {
        $html = '<html><head><style>'.$this->stylesBase().'</style></head><body>';
        $html .= $this->enTeteEcole($donnees['school']);

        $html .= '<div style="text-align:center;margin:4mm 0;">'
            .'<div class="titre">Bulletin annuel archivé</div>'
            .'<div class="titre-en">Archived annual report card</div>'
            .'</div>';

        $html .= '<table class="no-border"><tr>'
            .'<td class="no-border left">Élève / Student : <b>'.$this->e($donnees['eleve']['nom_complet']).'</b></td>'
            .'<td class="no-border left">Matricule : <b>'.$this->e((string) ($donnees['eleve']['matricule'] ?? '—')).'</b></td>'
            .'</tr><tr>'
            .'<td class="no-border left">Classe / Class : <b>'.$this->e($donnees['classe']['nom']).'</b></td>'
            .'<td class="no-border left">Année scolaire / School year : <b>'.$this->e($donnees['annee']['libelle']).'</b></td>'
            .'</tr></table>';

        foreach ($donnees['trimestres'] as $trimestre) {
            $html .= '<h3 class="titre" style="font-size:3.2mm;margin-top:5mm;">'.$this->e($trimestre['libelle']).'</h3>';
            $html .= '<table><thead><tr><th class="left">Matière / Subject</th><th>Coef.</th><th>Moyenne / Average</th><th>Rang / Rank</th></tr></thead><tbody>';

            foreach ($trimestre['matieres'] ?? [] as $ligne) {
                $html .= '<tr><td class="left">'.$this->e($ligne['matiere']).'</td>'
                    .'<td>'.$this->nombre($ligne['coefficient'] ?? null, 1).'</td>'
                    .'<td>'.$this->nombre($ligne['moyenne']).'</td>'
                    .'<td>'.$this->e($ligne['rang'] !== null ? (string) $ligne['rang'] : '—').'</td></tr>';
            }

            $html .= '</tbody></table>';
            $html .= '<p class="mini">Moyenne générale / Overall average : <span class="value">'.$this->nombre($trimestre['moyenne_generale'] ?? null)
                .'</span> — Rang / Rank : <span class="value">'.$this->e($trimestre['rang_general'] !== null ? (string) $trimestre['rang_general'] : '—').'</span></p>';
        }

        $html .= '<h3 class="titre" style="font-size:3.2mm;margin-top:5mm;">Synthèse annuelle / Annual summary</h3>';
        $html .= '<p>Moyenne annuelle / Annual average : <span class="value">'.$this->nombre($donnees['moyenne_annuelle'] ?? null)
            .'</span> — Rang annuel / Annual rank : <span class="value">'.$this->e(($donnees['rang_annuel'] ?? null) !== null ? (string) $donnees['rang_annuel'] : '—').'</span></p>';

        $html .= $this->signatureChef($donnees['school']);
        $html .= '</body></html>';

        return $html;
    }
}
