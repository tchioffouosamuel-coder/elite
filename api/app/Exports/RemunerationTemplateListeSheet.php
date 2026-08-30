<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Feuille « Liste » du modèle d'import des rémunérations — sert uniquement
 * de source à la liste déroulante de la feuille « Import » ; l'utilisateur
 * n'a rien à y saisir.
 */
class RemunerationTemplateListeSheet implements FromArray, WithHeadings, WithTitle
{
    /** @param  Collection<int, string>  $nomsPersonnel */
    public function __construct(private readonly Collection $nomsPersonnel) {}

    public function array(): array
    {
        return $this->nomsPersonnel->map(fn (string $nom) => [$nom])->all();
    }

    public function headings(): array
    {
        return ['Nom complet'];
    }

    public function title(): string
    {
        return 'Liste';
    }
}
