<?php

namespace App\Imports;

use App\Services\PreinscriptionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use RuntimeException;

/**
 * Import massif d'une campagne de réinscription : une ligne par élève,
 * ancien (matricule renseigné) ou nouveau (matricule vide, tuteur requis).
 * Chaque ligne est immédiatement validée via
 * {@see PreinscriptionService::importerLigne()} — l'admin a déjà revu son
 * fichier avant de l'importer, pas de file d'attente intermédiaire.
 *
 * ToCollection plutôt que ToModel : une ligne peut résoudre une classe par
 * nom et créer un tuteur, pas juste peupler un modèle.
 */
class PreinscriptionImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
        'matricule' => 'matricule',
        'nom_complet' => 'nom_complet',
        'sexe' => 'sexe',
        'date_naissance' => 'date_naissance',
        'classe' => 'classe',
        'nom_du_tuteur' => 'tuteur_nom',
        'tuteur_nom' => 'tuteur_nom',
        'telephone_du_tuteur' => 'tuteur_telephone',
        'tuteur_telephone' => 'tuteur_telephone',
        'montant_a_verser' => 'montant_verser',
        'montant_verser' => 'montant_verser',
        'mode_de_versement' => 'mode_versement',
        'mode_versement' => 'mode_versement',
    ];

    /** @return list<string> */
    public static function enTetes(): array
    {
        return [
            'Matricule', 'Nom complet', 'Sexe', 'Date de naissance', 'Classe',
            'Nom du tuteur', 'Téléphone du tuteur', 'Montant à verser', 'Mode de versement',
        ];
    }

    public int $importees = 0;

    /** @var list<array{ligne: int, message: string}> */
    public array $erreurs = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly PreinscriptionService $service,
        private readonly int $adminUserId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $numeroLigne = $index + 2; // +1 pour l'en-tête, +1 pour repasser en base 1.
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);

            try {
                $this->service->importerLigne($this->schoolId, $ligne, $this->adminUserId);
                $this->importees++;
            } catch (RuntimeException $e) {
                $this->erreurs[] = ['ligne' => $numeroLigne, 'message' => $e->getMessage()];
            }
        }
    }

    /** @param array<string, mixed> $donnees */
    private function normaliser(array $donnees): array
    {
        $ligne = [];

        foreach ($donnees as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            $valeur = ($valeur === '' || $valeur === null) ? null : $valeur;

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        if (isset($ligne['date_naissance']) && $ligne['date_naissance'] instanceof \DateTimeInterface) {
            $ligne['date_naissance'] = $ligne['date_naissance']->format('Y-m-d');
        }

        if (isset($ligne['sexe'])) {
            $ligne['sexe'] = mb_strtoupper(mb_substr((string) $ligne['sexe'], 0, 1));
        }

        if (isset($ligne['montant_verser'])) {
            $nombre = preg_replace('/[^\d]/', '', (string) $ligne['montant_verser']);
            $ligne['montant_verser'] = $nombre === '' ? null : (int) $nombre;
        }

        return $ligne;
    }
}
