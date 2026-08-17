<?php

namespace Database\Seeders;

use App\Support\CataloguePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Les privilèges existants viennent désormais du catalogue applicatif
     * (App\Support\CataloguePermissions) : la liste suit les routes qui les
     * exigent, et le seeder ne fait que la refléter en base.
     *
     * `FonctionPermissionSeeder` réutilise ces mêmes ensembles pour composer
     * les groupes de privilèges des fonctions du référentiel — d'où la
     * visibilité publique.
     */
    public const ROLE_PERMISSIONS = [
        'admin_etablissement' => [
            'ecoles.manage',
            'personnel.view',
            'personnel.manage',
            'classes.view',
            'classes.manage',
            'niveaux.view',
            'niveaux.manage',
            'eleves.view',
            'eleves.manage',
            'pedagogie.view',
            'pedagogie.manage',
            'notes.view',
            'notes.create',
            'discipline.view',
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'finance.view',
            'finance.manage',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'annonces.publish',
            'dashboard.view',
            'emploi_du_temps.view',
            'emploi_du_temps.manage',
            'appel.manage',
        ],
        'censeur_sg' => [
            'personnel.view',
            'classes.view',
            'eleves.view',
            'pedagogie.view',
            'notes.view',
            'notes.create',
            'discipline.view',
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'dashboard.view',
            'emploi_du_temps.view',
            'emploi_du_temps.manage',
            'appel.manage',
        ],
        /*
         * Le surveillant général tient la discipline : absences, sanctions,
         * appel et bilan disciplinaire. Il consulte les bulletins sans les
         * publier et ne saisit pas de notes — c'est le censeur qui répond du
         * pédagogique. La table `classes` distinguait déjà les deux
         * responsables ; les rôles le font désormais aussi.
         */
        'surveillant_general' => [
            'personnel.view',
            'classes.view',
            'eleves.view',
            'discipline.view',
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'bulletins.view',
            'emploi_du_temps.view',
            'appel.manage',
            'annonces.view',
            'dashboard.view',
        ],
        'enseignant' => [
            'classes.view',
            'eleves.view',
            'pedagogie.view',
            'notes.view',
            'notes.create',
            'discipline.view',
            'annonces.view',
            'dashboard.view',
            'emploi_du_temps.view',
            'appel.manage',
        ],
        'econome' => [
            'eleves.view',
            'finance.view',
            'finance.manage',
            'annonces.view',
            'dashboard.view',
        ],
        'parent' => [
            'eleves.view',
            'notes.view',
            'discipline.view',
            'finance.view',
            'annonces.view',
        ],
        'eleve' => [
            'notes.view',
            'annonces.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalogue = CataloguePermissions::codes();

        foreach ($catalogue as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Un privilège retiré du catalogue ne protège plus rien : le laisser en
        // base le laisserait apparaître dans l'écran d'administration.
        Permission::where('guard_name', 'web')->whereNotIn('name', $catalogue)->delete();

        // super_admin : accès total, géré via Gate::before plutôt qu'une liste à maintenir.
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions($catalogue);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
