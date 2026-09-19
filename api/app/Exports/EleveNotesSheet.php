<?php

namespace App\Exports;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\MoyennePrimaireService;
use App\Services\MoyenneService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Moyenne générale par trimestre de l'année scolaire active — pas de moyenne matière par matière, pour rester lisible avec un élève par ligne. */
class EleveNotesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    private MoyenneService $moyenneService;

    private MoyennePrimaireService $moyennePrimaireService;

    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves)
    {
        $this->moyenneService = app(MoyenneService::class);
        $this->moyennePrimaireService = app(MoyennePrimaireService::class);
    }

    public function array(): array
    {
        $lignes = [];
        $trimestresParAnnee = [];

        foreach ($this->eleves as $eleve) {
            if (! $eleve->classe_id) {
                continue;
            }

            $anneeId = AnneeScolaire::where('school_id', $eleve->school_id)->where('is_active', true)->value('id');

            if (! $anneeId) {
                continue;
            }

            $trimestresParAnnee[$anneeId] ??= Trimestre::where('annee_scolaire_id', $anneeId)->orderBy('ordre')->get();

            foreach ($trimestresParAnnee[$anneeId] as $trimestre) {
                $moyenne = $eleve->school?->estSecondaire()
                    ? $this->moyenneService->moyenneGeneraleEleve($eleve, $trimestre)['moyenne']
                    : $this->moyennePrimaireService->moyenneGeneraleEleve($eleve, $trimestre)['moyenne'];

                $lignes[] = [
                    $eleve->matricule,
                    $eleve->nom_complet,
                    $eleve->classe?->nom,
                    $trimestre->libelle,
                    $moyenne,
                    $moyenne !== null ? $this->moyenneService->lettreCote($moyenne) : null,
                ];
            }
        }

        return $lignes;
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Classe', 'Trimestre', 'Moyenne générale', 'Cote'];
    }

    public function title(): string
    {
        return 'Résultats';
    }
}
