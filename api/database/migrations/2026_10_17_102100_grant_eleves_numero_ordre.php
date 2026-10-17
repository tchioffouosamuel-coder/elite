<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Point 19 de la punch-list Elites-tech : l'enseignant (et, comme pour
 * `eleves.photo`, le censeur/surveillant général sur ses classes) corrige le
 * numéro de liste d'un élève sans porter `eleves.update`. Même mécanique que
 * `grant_eleves_photo_to_enseignants`.
 */
return new class extends Migration
{
    private const ROLES = ['enseignant', 'censeur_sg', 'surveillant_general', 'super_admin'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'eleves.numero_ordre',
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
        Permission::where('name', 'eleves.numero_ordre')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
