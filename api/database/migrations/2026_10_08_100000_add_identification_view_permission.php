<?php

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
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
        'econome',
        'infirmier',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'identification.view',
            'guard_name' => 'web',
        ]);

        Role::whereIn('name', [...self::ROLES, 'super_admin'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        FonctionReferentiel::query()
            ->get()
            ->filter(fn (FonctionReferentiel $fonction) => in_array(FonctionRoles::role($fonction->label_fr), self::ROLES, true))
            ->each(fn (FonctionReferentiel $fonction) => $fonction->permissions()->syncWithoutDetaching([$permission->id]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'identification.view')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
