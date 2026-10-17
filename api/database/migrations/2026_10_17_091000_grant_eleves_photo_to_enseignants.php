<?php

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * BUG2 de la punch-list Elites-tech : un enseignant ne pouvait pas prendre ou
 * changer la photo d'un élève de sa classe — la route `POST
 * eleves/{id}/photo` n'était gardée que par `eleves.update`, qui ouvre aussi
 * l'édition complète de la fiche (nom, classe…), jamais accordée à
 * l'enseignant. On lui donne ici un privilège dédié, plus étroit, sur le
 * même modèle que `eleves.situation` (cf.
 * grant_eleves_situation_to_enseignants) — le périmètre (classes où il
 * enseigne) reste appliqué par `Eleve::dansPerimetre()` dans
 * `EleveService::find()`, indépendamment du privilège.
 */
return new class extends Migration
{
    private const ROLE = 'enseignant';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'eleves.photo',
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
        Permission::where('name', 'eleves.photo')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
