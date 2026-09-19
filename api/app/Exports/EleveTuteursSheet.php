<?php

namespace App\Exports;

use App\Models\Eleve;
use App\Models\Tuteur;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Une ligne par couple élève/tuteur — un élève ayant plusieurs tuteurs (père, mère, tuteur légal...) apparaît sur plusieurs lignes. */
class EleveTuteursSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $lignes = [];

        foreach ($this->eleves as $eleve) {
            foreach ($eleve->tuteurs as $tuteur) {
                /** @var Tuteur&object{pivot: object} $tuteur */
                $telephones = $tuteur->telephones->pluck('numero')->filter()->implode(', ');

                $lignes[] = [
                    $eleve->matricule,
                    $eleve->nom_complet,
                    $tuteur->nom_complet,
                    $tuteur->pivot->lien_parente,
                    $tuteur->pivot->is_principal ? 'Oui' : 'Non',
                    $telephones !== '' ? $telephones : $tuteur->telephone,
                    $tuteur->email,
                    $tuteur->profession,
                    $tuteur->lieu_service,
                    $tuteur->adresse,
                ];
            }
        }

        return $lignes;
    }

    public function headings(): array
    {
        return [
            'Matricule élève', 'Nom élève', 'Nom du tuteur', 'Lien de parenté', 'Tuteur principal',
            'Téléphone(s)', 'Email', 'Profession', 'Lieu de service', 'Adresse',
        ];
    }

    public function title(): string
    {
        return 'Tuteurs';
    }
}
