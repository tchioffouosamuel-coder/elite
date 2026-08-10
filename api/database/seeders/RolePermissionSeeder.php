<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Taxonomie de permissions du MVP (notation pointée module.action).
     * Étendue au fil des paliers plutôt que définie exhaustivement d'avance.
     */
    private const PERMISSIONS = [
        'ecoles.manage',
        'personnel.view', 'personnel.manage',
        'classes.view', 'classes.manage',
        'eleves.view', 'eleves.manage',
        'pedagogie.view', 'pedagogie.manage',
        'notes.view', 'notes.create',
        'discipline.view', 'discipline.manage',
        'finance.view', 'finance.manage',
        'bulletins.view', 'bulletins.publish',
        'annonces.view', 'annonces.publish',
        'dashboard.view',
    ];

    private const ROLE_PERMISSIONS = [
        'admin_etablissement' => [
            'ecoles.manage', 'personnel.view', 'personnel.manage', 'classes.view', 'classes.manage',
            'eleves.view', 'eleves.manage', 'pedagogie.view', 'pedagogie.manage',
            'notes.view', 'notes.create', 'discipline.view', 'discipline.manage',
            'finance.view', 'finance.manage', 'bulletins.view', 'bulletins.publish',
            'annonces.view', 'annonces.publish', 'dashboard.view',
        ],
        'censeur_sg' => [
            'personnel.view', 'classes.view', 'eleves.view', 'pedagogie.view',
            'notes.view', 'notes.create', 'discipline.view', 'discipline.manage', 'bulletins.view', 'bulletins.publish',
            'annonces.view', 'dashboard.view',
        ],
        'enseignant' => [
            'classes.view', 'eleves.view', 'pedagogie.view', 'notes.view', 'notes.create',
            'discipline.view', 'annonces.view', 'dashboard.view',
        ],
        'econome' => [
            'eleves.view', 'finance.view', 'finance.manage', 'annonces.view', 'dashboard.view',
        ],
        'parent' => [
            'eleves.view', 'notes.view', 'discipline.view', 'finance.view', 'annonces.view',
        ],
        'eleve' => [
            'notes.view', 'annonces.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // super_admin : accès total, géré via Gate::before plutôt qu'une liste à maintenir.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
