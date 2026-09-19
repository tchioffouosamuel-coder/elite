<?php

namespace App\Exports;

use App\Models\DossierFraisAnnexe;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Détail des frais annexes (inscription, cantine, examens...) facturés sur chaque dossier de scolarité. */
class EleveFraisAnnexesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $lignes = DossierFraisAnnexe::whereHas('dossier', fn ($q) => $q->whereIn('eleve_id', $this->eleves->pluck('id')))
            ->with('dossier.anneeScolaire')
            ->get();

        return $lignes->map(function (DossierFraisAnnexe $ligne) use ($eleveParId) {
            $eleve = $eleveParId->get($ligne->dossier->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $ligne->dossier->anneeScolaire?->libelle,
                $ligne->libelle,
                $ligne->montant,
            ];
        })->all();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Année scolaire', 'Frais annexe', 'Montant'];
    }

    public function title(): string
    {
        return 'Frais annexes';
    }
}
