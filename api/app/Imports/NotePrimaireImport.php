<?php

namespace App\Imports;

use App\Services\NotePrimaireService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Colonnes attendues (en-têtes insensibles à la casse/accents, cf.
 * `Str::slug`) : `matricule`, `nom complet` (ignorée), puis une colonne
 * « {Volet} S{ordre} » par volet × séquence retenue — même construction que
 * {@see NotePrimaireService::lignesExport()}, pour un aller-retour sans
 * adaptation. En maternelle, la cellule attend le libellé d'une appréciation
 * du référentiel de l'école (ex. « Acquis »), pas un chiffre.
 *
 * Ne touche pas la base : les lignes reconnues sont seulement collectées
 * dans {@see $lignes}, pour que {@see NotePrimaireService::importerDepuisExcel()}
 * puisse détecter un remplacement de note déjà saisie avant d'écrire quoi
 * que ce soit (cf. `App\Services\Concerns\DetecteConflitsRemplacement`).
 */
class NotePrimaireImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{eleve_id:int, sequence_id:int, composante:string, valeur?: float, appreciation_id?: int}> */
    public array $lignes = [];

    /** @var list<string> */
    public array $erreurs = [];

    /**
     * @param  Collection<string, int>  $eleveIdsParMatricule  matricule normalisé => eleve_id, scopé à la classe
     * @param  list<string>  $composantes
     * @param  Collection<int, \App\Models\Sequence>  $sequences  dans l'ordre du trimestre (index 0 = séquence 1)
     * @param  Collection<string, int>  $appreciationsParLabel  libellé normalisé => appreciation_id
     * @param  array<string, float>  $repartition  plafond par volet (primaire uniquement)
     */
    public function __construct(
        private readonly Collection $eleveIdsParMatricule,
        private readonly array $composantes,
        private readonly Collection $sequences,
        private readonly bool $maternelle,
        private readonly Collection $appreciationsParLabel,
        private readonly array $repartition,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $matricule = trim((string) ($row['matricule'] ?? ''));

            if ($matricule === '') {
                continue;
            }

            $eleveId = $this->eleveIdsParMatricule->get(NotePrimaireService::normaliser($matricule));

            if ($eleveId === null) {
                $this->erreurs[] = "Matricule inconnu : {$matricule}";

                continue;
            }

            foreach ($this->composantes as $composante) {
                foreach ($this->sequences as $i => $sequence) {
                    $cle = Str::slug(NotePrimaireService::colonne($composante, $i + 1), '_');

                    if (! $row->has($cle)) {
                        continue;
                    }

                    $brut = $row->get($cle);

                    if ($brut === null || $brut === '') {
                        continue;
                    }

                    if ($this->maternelle) {
                        $appreciationId = $this->appreciationsParLabel->get(NotePrimaireService::normaliser((string) $brut));

                        if ($appreciationId === null) {
                            $this->erreurs[] = "{$matricule} — {$composante} S".($i + 1).' : appréciation inconnue « '.$brut.' ».';

                            continue;
                        }

                        $this->lignes[] = [
                            'eleve_id' => $eleveId, 'sequence_id' => $sequence->id,
                            'composante' => $composante, 'appreciation_id' => $appreciationId,
                        ];

                        continue;
                    }

                    if (! is_numeric($brut)) {
                        $this->erreurs[] = "{$matricule} — {$composante} S".($i + 1).' : valeur non numérique « '.$brut.' ».';

                        continue;
                    }

                    $valeur = (float) $brut;
                    $plafond = $this->repartition[$composante] ?? null;

                    if ($valeur < 0 || ($plafond !== null && $valeur > $plafond)) {
                        $this->erreurs[] = "{$matricule} — {$composante} S".($i + 1).' : '.$valeur.' hors barème (0 à '.$plafond.').';

                        continue;
                    }

                    $this->lignes[] = [
                        'eleve_id' => $eleveId, 'sequence_id' => $sequence->id,
                        'composante' => $composante, 'valeur' => $valeur,
                    ];
                }
            }
        }
    }
}
