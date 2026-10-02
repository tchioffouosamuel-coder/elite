<?php

use App\Support\AnciensPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les privilèges `X.manage` englobaient créer, modifier, supprimer, importer…
 * d'un module entier : impossible de laisser un agent corriger une fiche sans
 * lui donner aussi le droit de la supprimer. Chaque route exige désormais un
 * privilège par action (cf. CataloguePermissions).
 *
 * Cette migration bascule l'existant : tout rôle, compte ou fonction du
 * référentiel qui détenait un ancien code reçoit l'ensemble de ses
 * remplaçants (cf. AnciensPrivileges::REMPLACEMENTS), puis l'ancien code
 * disparaît. Personne ne perd ni ne gagne un accès — c'est ensuite au super
 * administrateur de retirer, fonction par fonction, les actions de trop.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            foreach (AnciensPrivileges::REMPLACEMENTS as $ancien => $remplacants) {
                // Créés même si l'ancien code n'existe pas sur cette base : les
                // notifications ciblées par privilège (cf.
                // NotificationService::notifierParPermission()) exigent la ligne.
                $nouveaux = array_map(fn (string $code) => $this->permission($code)->id, $remplacants);

                $source = Permission::where('name', $ancien)->where('guard_name', 'web')->first();

                if ($source === null) {
                    continue;
                }

                $this->reporter($source->id, $nouveaux);

                // Les pivots suivent (clés étrangères en cascade).
                $source->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Retour arrière : un détenteur d'au moins un remplaçant retrouve l'ancien
     * code global — on ne peut pas rendre moins que « tout le module », seule
     * granularité qu'il connaissait.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            foreach (AnciensPrivileges::REMPLACEMENTS as $ancien => $remplacants) {
                $cible = $this->permission($ancien)->id;

                $sources = Permission::whereIn('name', $remplacants)->where('guard_name', 'web')->get();

                foreach ($sources as $source) {
                    $this->reporter($source->id, [$cible]);
                    $source->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permission(string $code): Permission
    {
        return Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
    }

    /**
     * Accorde les privilèges `$cibles` à tout détenteur de `$sourceId` : rôles,
     * attributions directes et fonctions du référentiel.
     *
     * @param  list<int>  $cibles
     */
    private function reporter(int $sourceId, array $cibles): void
    {
        $tables = config('permission.table_names');
        $cleModele = config('permission.column_names.model_morph_key');

        $roles = DB::table($tables['role_has_permissions'])->where('permission_id', $sourceId)->pluck('role_id');
        $modeles = DB::table($tables['model_has_permissions'])->where('permission_id', $sourceId)->get(['model_type', $cleModele]);
        $fonctions = DB::table('fonction_permission')->where('permission_id', $sourceId)->pluck('fonction_referentiel_id');

        foreach ($cibles as $cible) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore(
                $roles->map(fn ($roleId) => ['permission_id' => $cible, 'role_id' => $roleId])->all(),
            );

            DB::table($tables['model_has_permissions'])->insertOrIgnore(
                $modeles->map(fn ($modele) => [
                    'permission_id' => $cible,
                    'model_type' => $modele->model_type,
                    $cleModele => $modele->{$cleModele},
                ])->all(),
            );

            DB::table('fonction_permission')->insertOrIgnore(
                $fonctions->map(fn ($fonctionId) => ['permission_id' => $cible, 'fonction_referentiel_id' => $fonctionId])->all(),
            );
        }
    }
};
