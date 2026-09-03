<?php

namespace App\Support\Pdf;

use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Procès-verbal du conseil de classe de fin d'année : seuil retenu, listes
 * admis/redoublants/exclus/graciés avec motifs, signature du chef
 * d'établissement. Reçoit un tableau déjà mis en forme par ConseilClasseController
 * (pas le modèle Eloquent directement, pour rester découplé du schéma comme
 * les autres générateurs).
 *
 * @param array{
 *   school: \App\Models\School,
 *   classe: \App\Models\Classe,
 *   annee: \App\Models\AnneeScolaire,
 *   seuil_moyenne: float,
 *   motif_seuil: ?string,
 *   classe_destination: ?string,
 *   valide_le: ?string,
 *   admis: list<array{nom_complet:string, matricule:?string, moyenne_annuelle:?float, gracie:bool, motif:?string}>,
 *   redoublants: list<array{nom_complet:string, matricule:?string, moyenne_annuelle:?float}>,
 *   exclus: list<array{nom_complet:string, matricule:?string, motif:?string}>,
 * } $donnees
 */
class ProcesVerbalConseilGenerator
{
    use RenduDocument;

    public function build(array $donnees): string
    {
        $mpdf = MpdfFactory::make([
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 10,
        ], $donnees['school']);
        $mpdf->SetTitle('PV Conseil de classe — '.$donnees['classe']->nom.' — '.$donnees['annee']->libelle);

        MpdfFactory::appliquerFiligrane($mpdf, $donnees['school'], largeurMm: 110);

        $mpdf->WriteHTML($this->html($donnees));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function html(array $donnees): string
    {
        $html = '<html><head><style>'.$this->stylesBase().'</style></head><body>';
        $html .= $this->enTeteEcole($donnees['school']);

        $html .= '<div style="text-align:center;margin:4mm 0;">'
            .'<div class="titre">Procès-verbal du conseil de classe</div>'
            .'<div class="titre-en">Class council minutes</div>'
            .'</div>';

        $html .= '<table class="no-border"><tr>'
            .'<td class="no-border left">Classe / Class : <b>'.$this->e($donnees['classe']->nom).'</b></td>'
            .'<td class="no-border left">Année scolaire / School year : <b>'.$this->e($donnees['annee']->libelle).'</b></td>'
            .'</tr><tr>'
            .'<td class="no-border left">Seuil de passage / Promotion threshold : <b>'.$this->nombre($donnees['seuil_moyenne']).'/20</b></td>'
            .'<td class="no-border left">Classe de destination / Destination class : <b>'.$this->e($donnees['classe_destination'] ?? 'Fin de cycle / End of cycle').'</b></td>'
            .'</tr></table>';

        if (! empty($donnees['motif_seuil'])) {
            $html .= '<p class="mini">Motif du seuil retenu / Reason for the threshold : '.$this->e($donnees['motif_seuil']).'</p>';
        }

        $html .= $this->sectionListe(
            'Admis en classe supérieure / Promoted',
            $donnees['admis'],
            fn ($ligne) => $this->nombre($ligne['moyenne_annuelle']).($ligne['gracie'] ? ' — Gracié / Pardoned : '.$this->e((string) $ligne['motif']) : ''),
        );

        $html .= $this->sectionListe(
            'Redoublants / Repeating',
            $donnees['redoublants'],
            fn ($ligne) => $this->nombre($ligne['moyenne_annuelle']),
        );

        if (! empty($donnees['exclus'])) {
            $html .= $this->sectionListe(
                'Exclus / Excluded',
                $donnees['exclus'],
                fn ($ligne) => $this->e((string) $ligne['motif']),
            );
        }

        $html .= $this->signatureChef($donnees['school']);
        $html .= '</body></html>';

        return $html;
    }

    /** @param callable(array): string $colonne */
    private function sectionListe(string $titre, array $lignes, callable $colonne): string
    {
        if (empty($lignes)) {
            return '<h3 class="titre" style="font-size:3.2mm;margin-top:5mm;">'.$this->e($titre).'</h3><p class="mini">Aucun élève. / None.</p>';
        }

        $html = '<h3 class="titre" style="font-size:3.2mm;margin-top:5mm;">'.$this->e($titre).' ('.count($lignes).')</h3>';
        $html .= '<table><thead><tr><th>Matricule</th><th class="left">Nom complet / Full name</th><th>Détail / Detail</th></tr></thead><tbody>';

        foreach ($lignes as $ligne) {
            $html .= '<tr><td>'.$this->e((string) ($ligne['matricule'] ?? '—')).'</td>'
                .'<td class="left">'.$this->e($ligne['nom_complet']).'</td>'
                .'<td>'.$colonne($ligne).'</td></tr>';
        }

        return $html.'</tbody></table>';
    }
}
