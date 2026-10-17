<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Point 15 de la punch-list Elites-tech : tableau de suivi transverse des
 * notes manquantes et des leçons en retard, pour la séquence active —
 * réservé à la direction (admin_ecole, admin_college, censeur_sg), pas à
 * l'enseignant ordinaire qui porte déjà `notes.view`/`notes.create`. Même
 * mécanique que `grant_eleves_photo_to_enseignants` : le catalogue
 * applicatif (`App\Support\CataloguePermissions`) a déjà le nouveau code,
 * cette migration le pousse en base pour les environnements déjà déployés,
 * sans attendre un nouveau `db:seed`.
 */
return new class extends Migration
{
    private const ROLES = ['admin_ecole', 'admin_college', 'censeur_sg', 'super_admin'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'pedagogie.suivi_saisies',
            'guard_name' => 'web',
        ]);

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'pedagogie.suivi_saisies')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
