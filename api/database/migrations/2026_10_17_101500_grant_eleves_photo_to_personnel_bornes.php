<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Point 20 de la punch-list Elites-tech : « tout enseignant ou membre du
 * personnel affecté à une classe » doit pouvoir prendre/changer la photo
 * d'un élève de cette classe — pas seulement l'enseignant. `censeur_sg` et
 * `surveillant_general` sont les deux autres rôles dont le périmètre est
 * nominatif ({@see \App\Support\Perimetre::ROLES_BORNES}) : un surveillant
 * général ou un censeur chargé de certaines classes doit avoir la même
 * capacité sur ses classes, déjà bornées par `Eleve::dansPerimetre()`
 * indépendamment de ce privilège. Même mécanique que
 * `grant_eleves_photo_to_enseignants`.
 */
return new class extends Migration
{
    private const ROLES = ['censeur_sg', 'surveillant_general'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'eleves.photo',
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
        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) {
                $permission = Permission::where('name', 'eleves.photo')->where('guard_name', 'web')->first();
                if ($permission) {
                    $role->revokePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
