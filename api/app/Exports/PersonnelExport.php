<?php

namespace App\Exports;

use App\Models\Personnel;
use App\Imports\PersonnelImport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PersonnelExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly int|array $schoolId) {}

    public function collection(): Collection
    {
        return Personnel::forSchool($this->schoolId)->with('departement', 'banqueReference')->orderBy('nom_complet')->get();
    }

    public function headings(): array
    {
        return PersonnelImport::enTetes();
    }

    public function map($personnel): array
    {
        return [
            $personnel->nom_complet,
            $personnel->civilite,
            $personnel->matricule,
            $personnel->numero_cni,
            $personnel->numero_cnps,
            $personnel->date_naissance?->format('Y-m-d'),
            $personnel->date_embauche?->format('Y-m-d'),
            $personnel->date_fin?->format('Y-m-d'),
            $personnel->date_retraite?->format('Y-m-d'),
            $personnel->departement_origine,
            $personnel->residence,
            $personnel->telephone,
            $personnel->telephone_2,
            $personnel->situation_matrimoniale,
            $personnel->nombre_enfants,
            $personnel->diplome_professionnel,
            $personnel->diplome_academique,
            $personnel->affectation,
            $personnel->departement?->nom,
            $personnel->numero_permis,
            $personnel->type_contrat,
            $personnel->statut_contrat,
            $personnel->categorie_echelon,
            $personnel->grade_minedub,
            $personnel->absent_depuis?->format('Y-m-d'),
            $personnel->motif_absence,
            $personnel->dossier_disciplinaire ? 'Oui' : 'Non',
            $personnel->date_deces?->format('Y-m-d'),
            $personnel->banque,
            $personnel->numero_compte,
            $personnel->pere_nom_complet,
            $personnel->pere_statut,
            $personnel->pere_telephone,
            $personnel->mere_nom_complet,
            $personnel->mere_statut,
            $personnel->mere_telephone,
        ];
    }
}
