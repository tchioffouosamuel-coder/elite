<?php

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * L'enseignant consulte, sur la fiche d'un élève de ses classes, sa situation
 * financière et son transport — en lecture seule, sans la caisse ni la
 * gestion de la flotte que donneraient `finance.view` / `bus.view`. On
 * l'accorde au rôle et aux fonctions du référentiel qui y correspondent.
 */
return new class extends Migration
{
    private const ROLE = 'enseignant';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'eleves.situation',
            'guard_name' => 'web',
        ]);

        // super_admin passe par Gate::before côté API, mais les clients (web,
        // mobile) lisent la liste de privilèges du rôle.
        Role::whereIn('name', [self::ROLE, 'super_admin'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        FonctionReferentiel::query()
            ->get()
            ->filter(fn (FonctionReferentiel $fonction) => FonctionRoles::role($fonction->label_fr) === self::ROLE)
            ->each(fn (FonctionReferentiel $fonction) => $fonction->permissions()->syncWithoutDetaching([$permission->id]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'eleves.situation')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
