<?php

namespace App\Support\Ocr;

use App\Models\Personnel;
use App\Models\PresencePersonnelJournaliere;

/**
 * Enregistre les lignes de présence relues et corrigées dans la modale de
 * prévisualisation (voir PresenceOcrExtractor), après validation manuelle de
 * l'utilisateur. Même effet que PresencePersonnelJournaliereImport (import
 * Excel), mais sur un tableau déjà résolu plutôt qu'une feuille à parser.
 */
class ConfirmationImportOcrPresence
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    /** @var array<int, array{ligne:int, message:string, nom:?string}> */
    public array $erreurs = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly string $datePresence,
    ) {}

    /** @param array<int, array{personnel_id:mixed, nom_complet?:?string, heure_arrivee:?string, heure_depart:?string}> $lignes */
    public function traiter(array $lignes): void
    {
        foreach ($lignes as $index => $ligne) {
            $personnel = Personnel::forSchool($this->schoolId)->find($ligne['personnel_id'] ?? null);

            if (! $personnel) {
                $this->erreurs[] = [
                    'ligne' => $index + 1,
                    'message' => 'Agent introuvable ou non rattaché à cet établissement.',
                    'nom' => $ligne['nom_complet'] ?? null,
                ];

                continue;
            }

            $arrivee = $ligne['heure_arrivee'] ?: null;
            $depart = $ligne['heure_depart'] ?: null;

            if ($arrivee && $depart && $depart <= $arrivee) {
                $this->erreurs[] = [
                    'ligne' => $index + 1,
                    'message' => "L'heure de départ doit être postérieure à l'heure d'arrivée.",
                    'nom' => $personnel->nom_complet,
                ];

                continue;
            }

            // `updateOrCreate` comparerait `date_presence` telle quelle à ce
            // qui est stocké en base (le cast Eloquent « date » sérialise en
            // "Y-m-d H:i:s" à l'écriture) : une simple chaîne "Y-m-d" ne
            // retrouverait jamais la ligne existante et ferait échouer
            // l'import sur la contrainte d'unicité dès la deuxième tentative
            // du même jour. `whereDate` compare la vraie valeur du jour, quel
            // que soit le format de stockage.
            $existante = PresencePersonnelJournaliere::where('school_id', $this->schoolId)
                ->where('personnel_id', $personnel->id)
                ->whereDate('date_presence', $this->datePresence)
                ->first();

            $valeurs = [
                'school_id' => $this->schoolId,
                'personnel_id' => $personnel->id,
                'date_presence' => $this->datePresence,
                'heure_arrivee' => $arrivee,
                'heure_depart' => $depart,
                'source' => 'import_ocr',
            ];

            if ($existante) {
                $existante->update($valeurs);
                $this->updatedCount++;
            } else {
                PresencePersonnelJournaliere::create($valeurs);
                $this->importedCount++;
            }
        }
    }
}
