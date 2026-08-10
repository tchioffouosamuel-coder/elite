<?php

namespace App\Services;

use App\Imports\EleveImport;
use App\Models\Eleve;
use App\Models\Tuteur;
use App\Repositories\EleveRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class EleveService extends BaseService
{
    public function __construct(private readonly EleveRepository $repository)
    {
    }

    public function list(int $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForSchool($schoolId, $filters, $perPage);
    }

    public function find(int $schoolId, int $id): Eleve
    {
        return $this->repository->query()->forSchool($schoolId)->with(['classe.niveau', 'tuteurs'])->findOrFail($id);
    }

    public function create(int $schoolId, array $attributes): Eleve
    {
        return $this->transaction(function () use ($schoolId, $attributes) {
            $tuteurs = $attributes['tuteurs'] ?? [];
            unset($attributes['tuteurs']);

            $eleve = $this->repository->create([...$attributes, 'school_id' => $schoolId]);
            $this->syncTuteurs($eleve, $schoolId, $tuteurs);

            return $eleve->load('tuteurs');
        });
    }

    public function update(Eleve $eleve, array $attributes): Eleve
    {
        return $this->transaction(function () use ($eleve, $attributes) {
            $tuteurs = $attributes['tuteurs'] ?? null;
            unset($attributes['tuteurs']);

            $eleve = $this->repository->update($eleve, $attributes);

            if ($tuteurs !== null) {
                $this->syncTuteurs($eleve, $eleve->school_id, $tuteurs);
            }

            return $eleve->load('tuteurs');
        });
    }

    /**
     * @return array{par_classe: array, par_genre: array, total: int}
     */
    public function repartition(int $schoolId): array
    {
        $parClasse = Eleve::forSchool($schoolId)
            ->selectRaw('classe_id, count(*) as total')
            ->groupBy('classe_id')
            ->with('classe:id,nom')
            ->get()
            ->map(fn ($row) => ['classe' => $row->classe?->nom ?? 'Non affecté', 'total' => $row->total]);

        $parGenre = Eleve::forSchool($schoolId)
            ->selectRaw('sexe, count(*) as total')
            ->groupBy('sexe')
            ->pluck('total', 'sexe');

        return [
            'par_classe' => $parClasse,
            'par_genre' => ['garcons' => $parGenre['M'] ?? 0, 'filles' => $parGenre['F'] ?? 0],
            'total' => Eleve::forSchool($schoolId)->count(),
        ];
    }

    /**
     * @return array{imported: int, failed: int, errors: array}
     */
    public function importFromExcel(int $schoolId, UploadedFile $file): array
    {
        $import = new EleveImport($schoolId);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];
    }

    private function syncTuteurs(Eleve $eleve, int $schoolId, array $tuteurs): void
    {
        $eleve->tuteurs()->detach();

        foreach ($tuteurs as $data) {
            $baseAttributes = [
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'] ?? null,
                'profession' => $data['profession'] ?? null,
                'adresse' => $data['adresse'] ?? null,
            ];

            $tuteur = ! empty($data['telephone'])
                ? Tuteur::firstOrCreate(['school_id' => $schoolId, 'telephone' => $data['telephone']], $baseAttributes)
                : Tuteur::create([...$baseAttributes, 'school_id' => $schoolId]);

            $eleve->tuteurs()->attach($tuteur->id, [
                'lien_parente' => $data['lien_parente'] ?? null,
                'is_principal' => $data['is_principal'] ?? false,
            ]);
        }
    }
}
