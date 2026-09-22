<?php

namespace App\Exports;

use App\Models\DossierScolarite;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SituationCaisseExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /** @param Collection<int, DossierScolarite> $dossiers */
    public function __construct(private readonly Collection $dossiers) {}

    public function array(): array
    {
        return $this->dossiers->map(function (DossierScolarite $dossier) {
            $eleve = $dossier->eleve;
            $classe = $eleve?->classe;

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $classe?->nom,
                $classe?->niveau_classe ?? $classe?->niveauScolaire?->libelle ?? $classe?->niveau?->name_fr,
                $this->telephoneParent($dossier, 'pere'),
                $this->telephoneParent($dossier, 'mere'),
                $dossier->montant_scolarite,
                $dossier->total_paye,
                $dossier->reste_a_payer,
                $dossier->remise,
                $dossier->report_dette,
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom', 'Classe', 'Niveau classe', 'Téléphone père', 'Téléphone mère',
            'Montant scolarité', 'Montant versé', 'Montant restant', 'Remise', 'Dette antérieure',
        ];
    }

    private function telephoneParent(DossierScolarite $dossier, string $parent): ?string
    {
        $tuteur = $dossier->eleve?->tuteurs->first(function ($tuteur) use ($parent) {
            $lien = Str::lower(Str::ascii((string) $tuteur->pivot?->lien_parente));

            return str_contains($lien, $parent);
        });

        return $tuteur?->telephone;
    }
}
