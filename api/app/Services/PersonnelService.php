<?php

namespace App\Services;

use App\Imports\PersonnelImport;
use App\Models\Personnel;
use App\Models\User;
use App\Repositories\PersonnelRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PersonnelService extends BaseService
{
    public function __construct(private readonly PersonnelRepository $repository) {}

    public function list(int $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForSchool($schoolId, $filters, $perPage);
    }

    public function find(int $schoolId, int $id): Personnel
    {
        return $this->repository->query()->forSchool($schoolId)->with(['departement', 'user'])->findOrFail($id);
    }

    public function create(int $schoolId, array $attributes): Personnel
    {
        return $this->repository->create([...$attributes, 'school_id' => $schoolId]);
    }

    public function update(Personnel $personnel, array $attributes): Personnel
    {
        return $this->repository->update($personnel, $attributes);
    }

    public function archive(Personnel $personnel): Personnel
    {
        return $this->repository->update($personnel, ['statut' => 'ex_employe']);
    }

    public function reactivate(Personnel $personnel): Personnel
    {
        return $this->repository->update($personnel, ['statut' => 'actif']);
    }

    /**
     * Crée un compte de connexion pour un membre du personnel et lui attribue un rôle.
     */
    public function createLoginAccount(Personnel $personnel, string $email, string $role, ?string $password = null): User
    {
        return $this->transaction(function () use ($personnel, $email, $role, $password) {
            $plainPassword = $password ?: Str::password(12);

            $user = User::create([
                'name' => $personnel->nomComplet(),
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'school_id' => $personnel->school_id,
                'is_active' => true,
            ]);
            $user->assignRole($role);

            $personnel->update(['user_id' => $user->id, 'email' => $email]);

            return $user;
        });
    }

    /**
     * Données du fichier du personnel : la liste des agents en poste et sa
     * ventilation. Comme `fichier_du_personnel.php` dans _smapp, le document
     * ne recense que le personnel actif — un fichier destiné à être signé et
     * archivé décrit l'effectif du moment, pas les départs.
     *
     * @return array{
     *     personnels: \Illuminate\Database\Eloquent\Collection<int, Personnel>,
     *     total: int,
     *     avec_acces: int,
     *     par_fonction: array<string, int>,
     *     par_departement: array<string, int>
     * }
     */
    public function fichier(int $schoolId): array
    {
        $personnels = Personnel::forSchool($schoolId)
            ->where('statut', 'actif')
            ->with('departement')
            ->orderBy('nom')->orderBy('prenom')
            ->get();

        $ventilation = static function (Collection $valeurs): array {
            $comptes = $valeurs->countBy()->all();
            arsort($comptes);

            return $comptes;
        };

        return [
            'personnels' => $personnels,
            'total' => $personnels->count(),
            'avec_acces' => $personnels->whereNotNull('user_id')->count(),
            'par_fonction' => $ventilation($personnels->map(fn (Personnel $p) => $p->fonction ?: 'Non précisée')),
            'par_departement' => $ventilation(
                $personnels->map(fn (Personnel $p) => $p->departement?->nom ?: 'Non rattaché')
            ),
        ];
    }

    /**
     * @return array{imported: int, failed: int, errors: array}
     */
    public function importFromExcel(int $schoolId, UploadedFile $file): array
    {
        $import = new PersonnelImport($schoolId);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];
    }
}
