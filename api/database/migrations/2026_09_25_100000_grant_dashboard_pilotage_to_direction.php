<?php

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Le pilotage en temps réel était ouvert à tout porteur de `dashboard.view`
 * (enseignants compris). Il passe derrière son propre privilège : on l'accorde
 * ici à la direction — rôles techniques et fonctions du référentiel qui y
 * correspondent — pour qu'elle ne le perde pas au déploiement. Les autres
 * fonctions peuvent le recevoir depuis l'écran de gestion des permissions.
 */
return new class extends Migration
{
    private const ROLES = [
        'admin_ecole',
        'admin_college',
        'censeur_sg',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'dashboard.pilotage',
            'guard_name' => 'web',
        ]);

        // super_admin passe par Gate::before côté API, mais les clients (web,
        // mobile) lisent la liste de privilèges du rôle pour afficher le bouton.
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
        Permission::where('name', 'dashboard.pilotage')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
