<?php

namespace App\Services\Concerns;

use Illuminate\Support\Collection;

/**
 * Détection de conflits avant un import en masse : une ligne entre en
 * conflit quand une valeur déjà en base pour la même clé diffère de celle
 * du fichier. Les imports qui utilisent ce trait ne doivent jamais écraser
 * silencieusement une valeur déjà saisie — ils écrivent uniquement si la
 * liste des conflits est vide, ou si le client a explicitement confirmé le
 * remplacement (cf. `NoteService::importFromExcel()`,
 * `NotePrimaireService::importerDepuisExcel()`,
 * `DisciplineService::importerAbsencesDepuisExcel()`).
 */
trait DetecteConflitsRemplacement
{
    /**
     * @param  list<array<string, mixed>>  $lignes  lignes validées du fichier
     * @param  Collection<int|string, mixed>  $existants  valeurs actuelles en base, indexées par la clé de rapprochement
     * @param  callable(array<string, mixed>): (int|string)  $cle  clé de rapprochement d'une ligne dans $existants
     * @param  callable(array<string, mixed>, mixed): bool  $sontDifferentes  vrai si la ligne remplacerait une valeur différente
     * @param  callable(array<string, mixed>, mixed): array<string, mixed>  $formatter  construit la ligne de rapport de conflit
     * @return list<array<string, mixed>>
     */
    protected function detecterConflits(array $lignes, Collection $existants, callable $cle, callable $sontDifferentes, callable $formatter): array
    {
        $conflits = [];

        foreach ($lignes as $ligne) {
            $existant = $existants->get($cle($ligne));

            if ($existant !== null && $sontDifferentes($ligne, $existant)) {
                $conflits[] = $formatter($ligne, $existant);
            }
        }

        return $conflits;
    }
}
