<?php

namespace App\Services;

use App\Imports\PersonnelImport;
use App\Models\Personnel;
use App\Models\User;
use App\Repositories\PersonnelRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
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
