<?php

namespace App\Exports;

use App\Models\BusAffectation;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EleveTransportSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $affectations = BusAffectation::whereIn('eleve_id', $this->eleves->pluck('id'))
            ->with(['trajet', 'arret', 'anneeScolaire', 'versements'])
            ->orderByDesc('annee_scolaire_id')
            ->get();

        return $affectations->map(function (BusAffectation $affectation) use ($eleveParId) {
            $eleve = $eleveParId->get($affectation->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $affectation->anneeScolaire?->libelle,
                $affectation->trajet?->nom,
                $affectation->arret?->nom,
                $affectation->option_trajet,
                $affectation->tarif_mensuel,
                $affectation->statut,
                $affectation->total_du,
                $affectation->total_paye,
                $affectation->reste_a_payer,
                $affectation->statut_paiement,
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Année scolaire', 'Trajet', 'Arrêt', 'Option de trajet', 'Tarif mensuel',
            'Statut affectation', 'Total dû', 'Total payé', 'Reste à payer', 'Statut paiement',
        ];
    }

    public function title(): string
    {
        return 'Transport';
    }
}
