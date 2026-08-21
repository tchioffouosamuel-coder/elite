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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class PersonnelService extends BaseService
{
    public function __construct(
        private readonly PersonnelRepository $repository,
        private readonly CompteAgentService $comptes,
    ) {}

    /** @param int|array<int> $schoolId */
    public function list(int|array $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForSchool($schoolId, $filters, $perPage);
    }

    /** @param int|array<int> $schoolId */
    public function find(int|array $schoolId, int $id): Personnel
    {
        return $this->repository->query()->forSchool($schoolId)->with(['departement', 'user', 'fonctionReference', 'school:id,name,code,type'])->findOrFail($id);
    }

    /**
     * L'accès de l'agent est ouvert dans la foulée : une fiche sans compte est
     * une fiche que personne ne peut utiliser, et l'oubli se découvrait le
     * jour où l'agent essayait de se connecter.
     */
    public function create(int $schoolId, array $attributes): Personnel
    {
        return $this->transaction(function () use ($schoolId, $attributes) {
            $personnel = $this->repository->create([...$attributes, 'school_id' => $schoolId]);
            $this->comptes->assurer($personnel);

            return $personnel->refresh();
        });
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
     * Ouverture manuelle d'un accès — pour les fiches créées avant que
     * l'ouverture soit automatique, ou pour imposer une adresse précise.
     *
     * Aucun rôle n'est attribué, ici non plus : un compte d'agent tient ses
     * droits de sa fonction. Attribuer en plus un rôle rendrait deux agents de
     * même fonction inégaux sans que rien ne l'explique à l'écran.
     */
    public function createLoginAccount(Personnel $personnel, ?string $email = null, ?string $password = null): User
    {
        return $this->transaction(function () use ($personnel, $email, $password) {
            if ($email !== null && trim($email) !== '') {
                $personnel->forceFill(['email' => trim($email)])->save();
            }

            $user = $this->comptes->assurer($personnel);

            if ($user === null) {
                throw new RuntimeException("Cet agent n'est plus en poste : aucun accès ne peut lui être ouvert.");
            }

            // Un mot de passe posé par un administrateur reste provisoire :
            // lui aussi devra être remplacé à la première connexion.
            if ($password !== null && trim($password) !== '') {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'doit_changer_mot_de_passe' => true,
                ])->save();
            }

            return $user;
        });
    }

    /**
     * Les affectations (titulaire, professeur principal, matières…) référencent
     * le personnel en `SET NULL` : elles sont donc simplement libérées, sans
     * bloquer la suppression. Le compte de connexion, lui, n'a pas de raison de
     * survivre à l'agent qu'il représentait.
     */
    public function delete(Personnel $personnel): void
    {
        $this->transaction(function () use ($personnel) {
            if ($personnel->photo_path) {
                Storage::disk('public')->delete($personnel->photo_path);
            }

            $personnel->user?->delete();

            $this->repository->delete($personnel);
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
    public function fichier(int|array $schoolId): array
    {
        $personnels = Personnel::forSchool($schoolId)
            ->where('statut', 'actif')
            ->with(['departement', 'fonctionReference', 'classesTenues.niveau'])
            ->orderBy('nom_complet')
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
            'par_fonction' => $ventilation($personnels->map(fn(Personnel $p) => $p->fonction ?: 'Non précisée')),
            'par_departement' => $ventilation(
                $personnels->map(fn(Personnel $p) => $p->departement?->nom ?: 'Non rattaché')
            ),
        ];
    }

    /**
     * @return array{imported: int, updated: int, comptes_ouverts: int, failed: int, errors: array, affectations_non_rattachees: array<string, int>}
     */
    public function importFromExcel(int $schoolId, UploadedFile $file): array
    {
        $import = new PersonnelImport($schoolId);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'comptes_ouverts' => $import->comptesOuverts,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
            'affectations_non_rattachees' => $import->affectationsNonRattachees,
        ];
    }
}
