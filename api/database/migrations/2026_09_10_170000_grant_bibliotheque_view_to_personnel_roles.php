<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ROLES = [
        'admin_ecole',
        'admin_college',
        'censeur_sg',
        'surveillant_general',
        'enseignant',
        'econome',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'bibliotheque.view',
            'guard_name' => 'web',
        ]);

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn(Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'bibliotheque.view')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn(Role $role) => $role->revokePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
