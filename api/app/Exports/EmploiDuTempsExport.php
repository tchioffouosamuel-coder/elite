<?php

namespace App\Exports;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export de l'emploi du temps d'UNE classe, dans la forme exacte que relit
 * {@see \App\Imports\EmploiDuTempsImport} : le fichier se corrige dans un
 * tableur et se réimporte tel quel.
 *
 * Seuls les créneaux PORTÉS par la classe sortent (pas ceux qu'elle rejoint
 * en tronc commun depuis une autre classe), même restriction que
 * `EmploiDuTempsService::genererSeances()`, qui ne matérialise lui aussi que
 * les créneaux dont la classe est la porteuse. Le tronc commun round-trip par
 * la colonne « Classes associées », semicolon-jointe comme la colonne
 * équivalente de `MatiereExport`.
 */
class EmploiDuTempsExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    private const LIBELLES_JOURS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
    ];

    public function __construct(private readonly Classe $classe) {}

    public function headings(): array
    {
        return ['Jour', 'Heure debut', 'Heure fin', 'Matiere', 'Enseignant', 'Salle', 'Classes associees'];
    }

    public function collection(): Collection
    {
        $creneaux = EmploiDuTemps::where('classe_id', $this->classe->id)
            ->with(['classeMatiere.matiere', 'classeMatiere.enseignant', 'classesAssociees'])
            ->orderBy('jour')
            ->orderBy('heure_debut')
            ->get();

        return $creneaux->map(fn (EmploiDuTemps $creneau) => [
            self::LIBELLES_JOURS[$creneau->jour] ?? $creneau->jour,
            substr((string) $creneau->heure_debut, 0, 5),
            substr((string) $creneau->heure_fin, 0, 5),
            $creneau->classeMatiere?->matiere?->nom,
            $creneau->classeMatiere?->enseignant?->nom_complet,
            $creneau->salle,
            $creneau->classesAssociees->pluck('nom')->implode('; '),
        ]);
    }
}
