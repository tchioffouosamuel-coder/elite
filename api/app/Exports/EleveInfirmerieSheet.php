<?php

namespace App\Exports;

use App\Models\Eleve;
use App\Models\VisiteInfirmerie;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EleveInfirmerieSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $visites = VisiteInfirmerie::whereIn('eleve_id', $this->eleves->pluck('id'))
            ->with('malaises')
            ->orderByDesc('date_visite')
            ->get();

        return $visites->map(function (VisiteInfirmerie $visite) use ($eleveParId) {
            $eleve = $eleveParId->get($visite->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $visite->date_visite?->format('Y-m-d H:i'),
                $visite->raison,
                $visite->malaises->pluck('label_fr')->implode(', '),
                $visite->soins_prodiges,
                $visite->type_traitement,
                $visite->structure_externe,
                $visite->cout_total,
                $visite->observations,
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Date de la visite', 'Raison', 'Malaises', 'Soins prodigués',
            'Type de traitement', 'Structure externe', 'Coût total', 'Observations',
        ];
    }

    public function title(): string
    {
        return 'Infirmerie';
    }
}
