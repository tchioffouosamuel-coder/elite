<?php

namespace App\Exports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export complet du dossier élève, une feuille par domaine plutôt qu'une
 * seule liste à plat : l'identité et la santé ne se lisent pas comme la
 * situation financière ou les absences, et tasser tout ça dans des colonnes
 * uniques rendrait le fichier illisible dès qu'un élève a plusieurs tuteurs,
 * plusieurs versements ou plusieurs années de scolarité.
 */
class EleveExport implements WithMultipleSheets
{
    /** @var Collection<int, Eleve> */
    private Collection $eleves;

    /** @param int|array<int> $schoolId */
    public function __construct(
        int|array $schoolId,
        ?int $classeId = null,
        bool $sansClasse = false,
    ) {
        $this->eleves = Eleve::forSchool($schoolId)
            ->when($classeId, fn ($q, $id) => $q->where('classe_id', $id))
            ->when($sansClasse, fn ($q) => $q->whereNull('classe_id'))
            ->with(['classe', 'school', 'tuteurs.telephones'])
            ->orderBy('nom_complet')
            ->get();
    }

    public function sheets(): array
    {
        return [
            new EleveIdentiteSheet($this->eleves),
            new EleveTuteursSheet($this->eleves),
            new EleveScolariteSheet($this->eleves),
            new EleveFraisAnnexesSheet($this->eleves),
            new EleveVersementsSheet($this->eleves),
            new EleveHistoriqueFinancierSheet($this->eleves),
            new EleveInfirmerieSheet($this->eleves),
            new EleveNotesSheet($this->eleves),
            new EleveTransportSheet($this->eleves),
            new EleveAbsencesSheet($this->eleves),
        ];
    }
}
