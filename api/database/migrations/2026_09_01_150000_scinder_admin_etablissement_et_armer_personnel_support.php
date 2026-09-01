<?php

use App\Models\FonctionReferentiel;
use App\Models\User;
use App\Support\FonctionRoles;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les seeders (RolePermissionSeeder, FonctionPermissionSeeder) ne sont pas
 * rejoués par le pipeline de déploiement — seul `php artisan migrate --force`
 * l'est (cf. docker/entrypoint.sh). Cette migration porte donc elle-même,
 * de façon idempotente, tout ce qui doit se produire une fois en production
 * pour la scission admin_etablissement → admin_ecole/admin_college :
 *
 * 1. Crée les rôles admin_ecole/admin_college (et les gabarits de fonctions
 *    de soutien infirmier/chauffeur/agent_securite/agent_entretien) à partir
 *    de RolePermissionSeeder::ROLE_PERMISSIONS, qui reste la seule source de
 *    vérité de leur contenu.
 * 2. Réaffecte chaque titulaire encore sur admin_etablissement vers le rôle
 *    correspondant, selon le type de son école.
 * 3. Applique les nouveaux gabarits de permissions aux fonctions du
 *    référentiel encore vierges (jamais à une fonction déjà personnalisée).
 *
 * Reprend exactement la logique des commandes Artisan
 * `roles:migrer-admin-etablissement` et `fonctions:appliquer-modeles-support`
 * (conservées à part pour un rejeu manuel ponctuel, par exemple après avoir
 * corrigé une fonction mal renseignée).
 */
return new class extends Migration
{
    private const ROLES_A_CREER = ['admin_ecole', 'admin_college', 'infirmier', 'chauffeur', 'agent_securite', 'agent_entretien'];

    private const FONCTIONS_SUPPORT = ['infirmier', 'chauffeur', 'agent_securite', 'agent_entretien'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES_A_CREER as $roleName) {
            $permissions = RolePermissionSeeder::ROLE_PERMISSIONS[$roleName] ?? [];

            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            // Les gabarits de fonctions de soutien ne sont jamais assignés à un
            // utilisateur : ils n'ont pas besoin d'une ligne Role Spatie, seule
            // FonctionPermissionSeeder/AppliquerModelesFonctionsSupport lisent
            // directement le tableau ROLE_PERMISSIONS.
            if (in_array($roleName, RolePermissionSeeder::FONCTIONS_SANS_ROLE, true)) {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->migrerTitulairesAdminEtablissement();
        $this->appliquerGabaritsFonctionsSupport();
    }

    public function down(): void
    {
        // Migration de données : recréer admin_etablissement et y renvoyer
        // les titulaires actuels d'admin_ecole/admin_college serait plus
        // risqué que de simplement documenter l'absence de rollback
        // automatique — à traiter manuellement si ce déploiement doit être
        // réellement annulé.
    }

    private function migrerTitulairesAdminEtablissement(): void
    {
        if (! Role::where('name', 'admin_etablissement')->exists()) {
            return;
        }

        User::role('admin_etablissement')->with('school')->get()->each(function (User $user) {
            if (! $user->school) {
                return;
            }

            $roleCible = $user->school->estSecondaire() ? 'admin_college' : 'admin_ecole';

            $user->removeRole('admin_etablissement');
            $user->assignRole($roleCible);
        });
    }

    private function appliquerGabaritsFonctionsSupport(): void
    {
        $idsParCode = Permission::where('guard_name', 'web')->pluck('id', 'name');

        FonctionReferentiel::query()
            ->withCount('permissions')
            ->get()
            ->each(function (FonctionReferentiel $fonction) use ($idsParCode) {
                if ($fonction->permissions_count > 0) {
                    return;
                }

                $role = FonctionRoles::role($fonction->label_fr);

                if (! in_array($role, self::FONCTIONS_SUPPORT, true)) {
                    return;
                }

                $codes = RolePermissionSeeder::ROLE_PERMISSIONS[$role] ?? [];
                $fonction->permissions()->sync($idsParCode->only($codes)->values());
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
