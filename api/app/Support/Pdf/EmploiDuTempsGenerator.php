<?php

namespace App\Support\Pdf;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Support\Pdf\Concerns\RenduDocument;
use App\Support\SignatureEmploiDuTemps;
use Endroid\QrCode\Builder\Builder;
use Mpdf\Output\Destination;

class EmploiDuTempsGenerator
{
    use RenduDocument;

    /** @param \Illuminate\Support\Collection<int, \App\Models\EmploiDuTemps> $creneaux */
    public function build(Classe $classe, AnneeScolaire $annee, $creneaux): string
    {
        $school = $classe->school;
        $mpdf = MpdfFactory::make([
            'orientation' => 'L',
            'format' => 'A4',
            'margin_top' => 8,
            'margin_bottom' => 10,
            'margin_left' => 8,
            'margin_right' => 8,
        ], $school);
        $mpdf->SetTitle('Emploi du temps - ' . $classe->nom);
        $mpdf->WriteHTML($this->html($classe, $annee, $creneaux));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function html(Classe $classe, AnneeScolaire $annee, $creneaux): string
    {
        $jours = [1 => ['Lundi', 'Monday'], 2 => ['Mardi', 'Tuesday'], 3 => ['Mercredi', 'Wednesday'], 4 => ['Jeudi', 'Thursday'], 5 => ['Vendredi', 'Friday'], 6 => ['Samedi', 'Saturday']];
        $lignes = $creneaux->map(fn($c) => substr((string) $c->heure_debut, 0, 5) . '|' . substr((string) $c->heure_fin, 0, 5))->unique()->sort()->values();
        $qr = (new Builder)->build(data: SignatureEmploiDuTemps::lienVerification($classe->id, $annee->id), size: 140, margin: 2);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . $this->stylesBase()
            . 'body{font-size:3mm}.edt th{background:#24557c;color:#fff;font-size:3.2mm;padding:2mm}.edt td{height:13mm;vertical-align:middle;padding:1mm}.cours{background:#dbe8f3}.matiere{font-weight:bold;font-size:3.2mm}.enseignant{font-size:2.7mm}.heure{font-style:italic;font-size:2.5mm}.vide{color:#888}.qr{width:16mm;height:16mm}</style></head><body>'
            . $this->enTeteEcole($classe->school)
            . '<div style="text-align:center;margin:1mm 0 3mm"><span class="titre">Emploi du temps - ' . $this->e($classe->nom) . '</span><br><span class="titre-en">Timetable - ' . $this->e($classe->nom) . '</span><br>Année scolaire / Academic year : <b>' . $this->e($annee->libelle) . '</b></div>'
            . '<table class="edt"><thead><tr><th>Heures<br><i>Time</i></th>';
        foreach ($jours as $jour) {
            $html .= '<th>' . $jour[0] . '<br><i>' . $jour[1] . '</i></th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($lignes as $ligne) {
            [$debut, $fin] = explode('|', $ligne);
            $html .= '<tr><th>' . $debut . '-' . $fin . '</th>';
            foreach (array_keys($jours) as $jour) {
                $cours = $creneaux->first(fn($c) => (int) $c->jour === $jour && substr((string) $c->heure_debut, 0, 5) === $debut && substr((string) $c->heure_fin, 0, 5) === $fin);
                $html .= $cours
                    ? '<td class="cours"><div class="matiere">' . $this->e($cours->classeMatiere?->matiere?->nom ?? '—') . '</div><div class="enseignant">' . $this->e($cours->classeMatiere?->enseignant?->nom_complet ?? '—') . '</div><div class="heure">' . $debut . '-' . $fin . '</div></td>'
                    : '<td class="vide">-</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table><table class="no-border" style="margin-top:2mm"><tr><td style="text-align:left;border:none;color:#666;font-style:italic">Case bleue / blue cell = cours programme / scheduled class &nbsp; | &nbsp; Case vide / empty cell = aucun cours / no class</td><td style="width:22%;border:none;text-align:right"><img class="qr" src="' . $qr->getDataUri() . '" /></td><td style="width:18%;border:none;text-align:left;font-size:2.3mm">Authenticité / Authenticity<br>Scannez pour vérifier<br>Scan to verify</td></tr></table></body></html>';

        return $html;
    }
}
