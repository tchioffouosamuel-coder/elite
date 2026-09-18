<?php

namespace App\Imports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import du fichier « matricules nationaux » complété par l'établissement —
 * cf. `MatriculeNationalModeleExport` pour le format attendu. Ne fait jamais
 * que mettre à jour un élève déjà existant, rapproché par son matricule
 * interne (`IDEleves`) : contrairement à `PreinscriptionImport`, une ligne
 * sans correspondance est une erreur, jamais l'occasion de créer un nouvel
 * élève — ce fichier ne porte ni classe complète, ni tuteur, rien qui
 * permette d'en ouvrir un dossier correct.
 */
class MatriculeNationalImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
        'ideleves' => 'matricule',
        'id_eleves' => 'matricule',
        'matricule' => 'matricule',
        'matricule_national' => 'matricule_national',
    ];

    /** @return list<string> */
    public static function enTetes(): array
    {
        return ['IDEleves', 'Nom complet', 'Classe', 'École', 'Matricule national'];
    }

    public int $importees = 0;

    /** @var list<array{ligne: int, message: string}> */
    public array $erreurs = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly int $adminUserId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $numeroLigne = $index + 2; // +1 pour l'en-tête, +1 pour repasser en base 1.
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);

            // Ligne sans matricule interne exploitable, ou dont la colonne
            // « Matricule national » n'a simplement pas été remplie (le cas
            // normal pour un élève toujours en attente) : rien à faire, pas
            // une erreur à signaler à l'admin.
            if ($ligne['matricule'] === null || $ligne['matricule_national'] === null) {
                continue;
            }

            $eleve = Eleve::where('school_id', $this->schoolId)->where('matricule', $ligne['matricule'])->first();

            if ($eleve === null) {
                $this->erreurs[] = ['ligne' => $numeroLigne, 'message' => "Aucun élève ne correspond au matricule « {$ligne['matricule']} »."];

                continue;
            }

            if (! $eleve->school?->estSecondaire()) {
                $this->erreurs[] = ['ligne' => $numeroLigne, 'message' => 'Le matricule national est réservé aux élèves du secondaire.'];

                continue;
            }

            $conflit = Eleve::where('matricule_national', $ligne['matricule_national'])->whereKeyNot($eleve->id)->exists();

            if ($conflit) {
                $this->erreurs[] = ['ligne' => $numeroLigne, 'message' => "Le matricule national « {$ligne['matricule_national']} » est déjà attribué à un autre élève."];

                continue;
            }

            $eleve->update(['matricule_national' => $ligne['matricule_national']]);
            $this->importees++;
        }
    }

    /** @param array<string, mixed> $donnees */
    private function normaliser(array $donnees): array
    {
        $ligne = ['matricule' => null, 'matricule_national' => null];

        foreach ($donnees as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? null;

            if ($cle === null) {
                continue;
            }

            if (is_string($valeur)) {
                $valeur = trim($valeur);
            }

            if ($valeur === '' || $valeur === null) {
                continue;
            }

            if ($ligne[$cle] === null) {
                $ligne[$cle] = (string) $valeur;
            }
        }

        return $ligne;
    }
}
