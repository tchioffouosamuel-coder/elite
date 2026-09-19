<?php

namespace App\Exports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EleveIdentiteSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        return $this->eleves->map(fn (Eleve $eleve) => [
            $eleve->matricule,
            $eleve->matricule_national,
            $eleve->nom_complet,
            $eleve->sexe,
            $eleve->date_naissance?->format('Y-m-d'),
            $eleve->age,
            $eleve->lieu_naissance,
            $eleve->nationalite,
            $eleve->classe?->nom,
            $eleve->statut,
            $eleve->redoublant ? 'Oui' : 'Non',
            $eleve->groupe_sanguin,
            $eleve->situation_sanitaire,
            $eleve->aptitude,
            $eleve->allergies,
            $eleve->handicap,
            $eleve->type_handicap,
            $eleve->adresse,
        ])->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Matricule national', 'Nom complet', 'Sexe', 'Date de naissance', 'Âge',
            'Lieu de naissance', 'Nationalité', 'Classe', 'Statut', 'Redoublant',
            'Groupe sanguin', 'Situation sanitaire', 'Aptitude', 'Allergies', 'Handicap', 'Type de handicap', 'Adresse',
        ];
    }

    public function title(): string
    {
        return 'Identité';
    }
}
