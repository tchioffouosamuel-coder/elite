<?php

namespace App\Exports;

use App\Models\DossierScolarite;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Un dossier de scolarité par année scolaire — un élève présent depuis plusieurs années a une ligne par année. */
class EleveScolariteSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $dossiers = DossierScolarite::whereIn('eleve_id', $this->eleves->pluck('id'))
            ->avecTotaux()
            ->with('anneeScolaire')
            ->orderByDesc('annee_scolaire_id')
            ->get();

        return $dossiers->map(function (DossierScolarite $dossier) use ($eleveParId) {
            $eleve = $eleveParId->get($dossier->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $eleve?->classe?->nom,
                $dossier->anneeScolaire?->libelle,
                $dossier->montant_scolarite,
                $dossier->remise,
                $dossier->scolarite_nette,
                $dossier->report_dette,
                $dossier->total_frais_annexes,
                $dossier->total_du,
                $dossier->total_paye,
                $dossier->reste_a_payer,
                $dossier->statut_paiement,
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Classe', 'Année scolaire', 'Montant scolarité', 'Remise', 'Scolarité nette',
            'Dette antérieure reportée', 'Total frais annexes', 'Total dû', 'Total payé', 'Reste à payer', 'Statut paiement',
        ];
    }

    public function title(): string
    {
        return 'Scolarité';
    }
}
