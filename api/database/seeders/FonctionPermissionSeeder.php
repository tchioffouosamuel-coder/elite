<?php

namespace Database\Seeders;

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dote chaque fonction du référentiel de son groupe de privilèges par défaut.
 *
 * Les ensembles sont ceux des rôles (cf. RolePermissionSeeder) : au moment de
 * bascule, un enseignant rattaché à la fonction « Enseignant » retrouve
 * exactement les droits du rôle `enseignant`. Le super administrateur ajuste
 * ensuite depuis l'écran des permissions ; ce seeder ne réécrit que les
 * fonctions encore vierges, pour ne jamais écraser un réglage fait à la main.
 *
 * La correspondance libellé → rôle vit dans `App\Support\FonctionRoles`,
 * partagée avec `User::estEnseignant()` : les deux doivent s'accorder sur ce
 * qui fait « la » fonction d'enseignant.
 */
class FonctionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $idsParCode = Permission::where('guard_name', 'web')->pluck('id', 'name');

        FonctionReferentiel::query()
            ->withCount('permissions')
            ->orderBy('id')
            ->get()
            ->each(function (FonctionReferentiel $fonction) use ($idsParCode) {
                if ($fonction->permissions_count > 0) {
                    return;
                }

                $role = FonctionRoles::role($fonction->label_fr);
                $codes = $role ? (RolePermissionSeeder::ROLE_PERMISSIONS[$role] ?? []) : [];

                $fonction->permissions()->sync($idsParCode->only($codes)->values());
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
