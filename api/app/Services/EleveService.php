<?php

namespace App\Services;

use App\Imports\EleveImport;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Tuteur;
use App\Repositories\EleveRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EleveService extends BaseService
{
    public function __construct(private readonly EleveRepository $repository) {}

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

            $attributes['matricule'] = ($attributes['matricule'] ?? null) ?: Eleve::genererMatricule($schoolId);

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
     * Rattache l'élève à une classe d'une autre école du complexe. Notes,
     * absences et sanctions restent liées aux classe_matieres de l'école
     * d'origine : l'historique scolaire est conservé tel quel, seul le
     * rattachement courant change.
     */
    public function transferer(Eleve $eleve, Classe $classe): Eleve
    {
        return $this->repository->update($eleve, [
            'school_id' => $classe->school_id,
            'classe_id' => $classe->id,
            'statut' => 'actif',
        ]);
    }

    /**
     * Recadre en carré (centre) et redimensionne en 600x600 JPEG, comme upload_photo.php dans _smapp.
     */
    public function updatePhoto(Eleve $eleve, UploadedFile $file): Eleve
    {
        $source = $file->getMimeType() === 'image/png'
            ? imagecreatefrompng($file->getRealPath())
            : imagecreatefromjpeg($file->getRealPath());

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = intdiv($width - $side, 2);
        $srcY = intdiv($height - $side, 2);

        $square = imagecreatetruecolor(600, 600);
        imagecopyresampled($square, $source, 0, 0, $srcX, $srcY, 600, 600, $side, $side);
        imagedestroy($source);

        ob_start();
        imagejpeg($square, null, 90);
        $contents = ob_get_clean();
        imagedestroy($square);

        $path = 'eleves/photos/' . $eleve->id . '.jpg';
        Storage::disk('public')->put($path, $contents);

        return $this->repository->update($eleve, ['photo_path' => $path]);
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
            ->map(fn($row) => ['classe' => $row->classe?->nom ?? 'Non affecté', 'total' => $row->total]);

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
     * `ignored` compte les lignes d'une autre école du complexe (le fichier de
     * situation couvre maternelle, primaire et secondaire d'un seul tenant) et
     * `classes_introuvables` les libellés de classe non rattachés : sans ce
     * retour, l'utilisateur ne verrait qu'un total d'import inexpliqué.
     *
     * @return array{imported: int, updated: int, ignored: int, failed: int, errors: array, classes_introuvables: array<string, int>}
     */
    public function importFromExcel(int $schoolId, UploadedFile $file): array
    {
        $import = new EleveImport($schoolId);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'ignored' => $import->ignoredCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
            'classes_introuvables' => $import->classesIntrouvables,
        ];
    }

    public function delete(Eleve $eleve): void
    {
        $this->transaction(function () use ($eleve) {
            // Détacher les tuteurs
            $eleve->tuteurs()->detach();

            // Supprimer la photo si elle existe
            if ($eleve->photo_path) {
                Storage::disk('public')->delete($eleve->photo_path);
            }

            // Supprimer l'élève
            $this->repository->delete($eleve);
        });
    }

    private function syncTuteurs(Eleve $eleve, int $schoolId, array $tuteurs): void
    {
        $eleve->tuteurs()->detach();

        foreach ($tuteurs as $data) {
            $baseAttributes = [
                'nom_complet' => $data['nom_complet'],
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
