<?php

namespace App\Exports;

use App\Models\AbsenceTrimestre;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EleveAbsencesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $absences = AbsenceTrimestre::whereIn('eleve_id', $this->eleves->pluck('id'))
            ->with('trimestre')
            ->get()
            ->sortBy(fn (AbsenceTrimestre $a) => $a->trimestre?->ordre);

        return $absences->map(function (AbsenceTrimestre $absence) use ($eleveParId) {
            $eleve = $eleveParId->get($absence->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $eleve?->classe?->nom,
                $absence->trimestre?->libelle,
                (float) $absence->heures_justifiees,
                (float) $absence->heures_non_justifiees,
                (float) $absence->heures_justifiees + (float) $absence->heures_non_justifiees,
            ];
        })->values()->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Classe', 'Trimestre',
            'Heures justifiées', 'Heures non justifiées', 'Total heures d\'absence',
        ];
    }

    public function title(): string
    {
        return 'Absences';
    }
}
