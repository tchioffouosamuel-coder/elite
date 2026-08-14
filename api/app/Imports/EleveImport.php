<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Tuteur;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Colonnes attendues (en-têtes insensibles à la casse) :
 * nom_complet, sexe (M/F), matricule, classe (nom exact de la classe),
 * date_naissance (AAAA-MM-JJ), lieu_naissance, tuteur_nom_complet, tuteur_telephone
 *
 * ToCollection plutôt que ToModel : contrairement à PersonnelImport, chaque
 * ligne doit résoudre une classe par nom et éventuellement créer un tuteur —
 * une simple correspondance ligne→modèle ne suffit pas.
 */
class EleveImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    public function __construct(private readonly int $schoolId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $classeId = null;
            if (! empty($row['classe'])) {
                $classeId = Classe::where('school_id', $this->schoolId)->where('nom', trim($row['classe']))->value('id');
            }

            $eleve = Eleve::create([
                'school_id' => $this->schoolId,
                'classe_id' => $classeId,
                'matricule' => ($row['matricule'] ?? null) ?: Eleve::genererMatricule($this->schoolId),
                'nom_complet' => $row['nom_complet'],
                'sexe' => strtoupper($row['sexe']),
                'date_naissance' => $row['date_naissance'] ?? null,
                'lieu_naissance' => $row['lieu_naissance'] ?? null,
                'statut' => 'actif',
            ]);

            if (! empty($row['tuteur_nom_complet'])) {
                $tuteur = ! empty($row['tuteur_telephone'])
                    ? Tuteur::firstOrCreate(
                        ['school_id' => $this->schoolId, 'telephone' => $row['tuteur_telephone']],
                        ['nom_complet' => $row['tuteur_nom_complet']]
                    )
                    : Tuteur::create([
                        'school_id' => $this->schoolId,
                        'nom_complet' => $row['tuteur_nom_complet'],
                    ]);

                $eleve->tuteurs()->attach($tuteur->id, ['lien_parente' => 'parent', 'is_principal' => true]);
            }

            $this->importedCount++;
        }
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['required', 'string'],
            'sexe' => ['required', 'in:M,F,m,f'],
            'date_naissance' => ['nullable', 'date'],
        ];
    }
}
