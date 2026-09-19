<?php

namespace App\Support\Pdf;

use App\Models\BusAffectation;
use App\Models\School;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Liste personnalisée des élèves transportés : mêmes affectations que
 * `ListeElevesBusGenerator`, mais filtrées en amont (classe, arrêt, sens,
 * nom…) et regroupables à l'affichage (une section par classe, trajet,
 * arrêt ou sens) au lieu d'être toujours scopées à un seul véhicule.
 */
class ListePersonnaliseeBusGenerator
{
    use RenduDocument;

    private const LIBELLES_OPTION = [
        'aller_simple' => 'Aller',
        'retour_simple' => 'Retour',
        'aller_retour' => 'Aller & retour',
    ];

    private const LIBELLES_GROUPE = [
        'classe' => 'Classe',
        'trajet' => 'Trajet',
        'arret' => 'Arrêt',
        'option_trajet' => 'Sens',
    ];

    /**
     * @param  Collection<int, BusAffectation>  $affectations
     * @param  string|null  $groupePar  'classe'|'trajet'|'arret'|'option_trajet'|null
     * @param  array<string, string>  $filtresLabel  libellés des filtres actifs, affichés dans le bandeau
     */
    public function build(Collection $affectations, ?string $groupePar, array $filtresLabel, School $ecole): string
    {
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $ecole);
        $mpdf->SetTitle('Liste personnalisée — transport scolaire');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<style>' . $this->stylesBase() . $this->stylesPropres() . '</style></head><body>'
                . $this->enTeteEcole($ecole)
                . '<hr>'
                . $this->titre($affectations, $filtresLabel)
                . $this->corps($affectations, $groupePar)
                . $this->signature($ecole)
                . '</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:' . self::ARDOISE . ';color:#fff;padding:2mm;text-align:center;'
            . 'font-size:3mm;font-weight:bold;margin:3mm 0}'
            . '.filtres{text-align:center;font-size:2.6mm;color:#555;margin-bottom:2mm}'
            . '.groupe-titre{background:#eef0f2;color:' . self::ARDOISE . ';padding:1.5mm 2mm;'
            . 'font-size:2.9mm;font-weight:bold;text-transform:uppercase;margin-top:4mm}'
            . '.liste th{font-size:2.6mm}'
            . '.liste td{font-size:2.7mm;padding:1.2mm 1mm}'
            . '.liste tbody tr:nth-child(even) td{background:#f7f7f5}'
            . '.nom{font-weight:bold;text-transform:uppercase}';
    }

    /**
     * @param  Collection<int, BusAffectation>  $affectations
     * @param  array<string, string>  $filtresLabel
     */
    private function titre(Collection $affectations, array $filtresLabel): string
    {
        $filtres = implode(' &nbsp;·&nbsp; ', array_map(
            fn($cle, $valeur) => $this->e($cle) . ' : ' . $this->e($valeur),
            array_keys($filtresLabel),
            $filtresLabel,
        ));

        return '<div style="text-align:center;line-height:1.4;">'
            . '<span class="titre">Liste personnalisée — transport scolaire</span><br>'
            . '<span class="titre-en">Custom transport list</span>'
            . '</div>'
            . ($filtres !== '' ? '<div class="filtres">' . $filtres . '</div>' : '')
            . '<div class="bandeau">Effectif <i>/ Headcount</i> : ' . $affectations->count() . '</div>';
    }

    /** @param  Collection<int, BusAffectation>  $affectations */
    private function corps(Collection $affectations, ?string $groupePar): string
    {
        if ($groupePar === null || ! isset(self::LIBELLES_GROUPE[$groupePar])) {
            return $this->tableau($affectations, true);
        }

        $groupes = $affectations->groupBy(fn(BusAffectation $a) => $this->cleGroupe($a, $groupePar));

        $html = '';
        foreach ($groupes as $cle => $lignesGroupe) {
            $html .= '<div class="groupe-titre">' . $this->e(self::LIBELLES_GROUPE[$groupePar]) . ' : '
                . $this->e((string) $cle) . ' (' . $lignesGroupe->count() . ')</div>'
                . $this->tableau($lignesGroupe, false, $groupePar);
        }

        return $html;
    }

    private function cleGroupe(BusAffectation $affectation, string $groupePar): string
    {
        return match ($groupePar) {
            'classe' => $affectation->eleve?->classe?->nom ?: 'Sans classe',
            'trajet' => $affectation->trajet?->nom ?: 'Sans trajet',
            'arret' => $affectation->arret?->nom ?: 'Sans arrêt',
            'option_trajet' => self::LIBELLES_OPTION[$affectation->option_trajet] ?? $affectation->option_trajet,
            default => '—',
        };
    }

    /**
     * @param  Collection<int, BusAffectation>  $affectations
     * @param  bool  $avecToutesColonnes  colonnes classe/trajet/arrêt/sens — masquées quand déjà portées par le titre du groupe
     */
    private function tableau(Collection $affectations, bool $avecToutesColonnes, ?string $colonneMasquee = null): string
    {
        $lignes = '';
        $rang = 1;

        foreach ($affectations->sortBy(fn(BusAffectation $a) => $a->eleve?->nom_complet) as $affectation) {
            $eleve = $affectation->eleve;

            $lignes .= '<tr>'
                . '<td>' . $rang . '</td>'
                . '<td class="left nom">' . $this->e($eleve?->nom_complet ?: '—') . '</td>'
                . ($colonneMasquee === 'classe' ? '' : '<td class="left">' . $this->e($eleve?->classe?->nom ?: '—') . '</td>')
                . ($colonneMasquee === 'trajet' ? '' : '<td class="left">' . $this->e($affectation->trajet?->nom ?: '—') . '</td>')
                . ($colonneMasquee === 'arret' ? '' : '<td class="left">' . $this->e($affectation->arret?->nom ?: '—') . '</td>')
                . ($colonneMasquee === 'option_trajet' ? '' : '<td>' . $this->e(self::LIBELLES_OPTION[$affectation->option_trajet] ?? $affectation->option_trajet) . '</td>')
                . '</tr>';
            $rang++;
        }

        $colspan = 6 - ($colonneMasquee !== null ? 1 : 0);

        if ($lignes === '') {
            $lignes = '<tr><td colspan="' . $colspan . '" style="padding:6mm;">Aucun élève dans ce groupe.</td></tr>';
        }

        return '<table class="liste"><thead><tr>'
            . '<th style="width:6%;">N°</th>'
            . '<th style="width:26%;">Nom et prénoms<br><i>Full name</i></th>'
            . ($colonneMasquee === 'classe' ? '' : '<th style="width:16%;">Classe<br><i>Class</i></th>')
            . ($colonneMasquee === 'trajet' ? '' : '<th style="width:20%;">Trajet<br><i>Route</i></th>')
            . ($colonneMasquee === 'arret' ? '' : '<th style="width:16%;">Arrêt<br><i>Stop</i></th>')
            . ($colonneMasquee === 'option_trajet' ? '' : '<th style="width:16%;">Sens<br><i>Direction</i></th>')
            . '</tr></thead><tbody>' . $lignes . '</tbody></table>';
    }

    private function signature(School $ecole): string
    {
        $ville = trim(explode(',', (string) $ecole->address)[0] ?? '');
        $lieu = $ville !== '' ? 'Fait à ' . $ville . ', le ' : 'Fait le ';

        return '<table class="no-border" style="margin-top:6mm;"><tr>'
            . '<td class="no-border left" style="width:50%;vertical-align:top;font-size:2.8mm;">'
            . $this->e($lieu) . date('d/m/Y')
            . '</td>'
            . '<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            . '<b>Responsable du transport scolaire</b><br><i>Transport officer</i>'
            . '<br><br><br><br>'
            . '<span style="border-top:0.4px solid #000;padding-top:1mm;">Signature</span>'
            . '</td></tr></table>';
    }
}
