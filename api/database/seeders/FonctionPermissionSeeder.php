<?php

namespace Database\Seeders;

use App\Models\FonctionReferentiel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
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
 */
class FonctionPermissionSeeder extends Seeder
{
    /**
     * Libellé de fonction (normalisé, sans accent ni casse) => rôle dont elle
     * reprend les privilèges. `null` = aucun accès à l'application : la
     * fonction existe pour le fichier du personnel, pas pour ouvrir un compte.
     */
    private const CORRESPONDANCES = [
        'enseignant' => 'enseignant',
        'principal' => 'admin_etablissement',
        'directeur' => 'admin_etablissement',
        'censeur' => 'censeur_sg',
        'surveillant general' => 'surveillant_general',
        'conseiller d orientation' => 'surveillant_general',
        'econome' => 'econome',
        'secretaire' => 'econome',
        'documentaliste' => null,
        'infirmier' => null,
        'gardien' => null,
        'agent d entretien' => null,
    ];

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

                $role = self::CORRESPONDANCES[self::normaliser($fonction->label_fr)] ?? null;
                $codes = $role ? (RolePermissionSeeder::ROLE_PERMISSIONS[$role] ?? []) : [];

                $fonction->permissions()->sync($idsParCode->only($codes)->values());
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** « Surveillant Général » et « surveillant general » désignent la même fonction. */
    private static function normaliser(string $label): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($label))) ?? '');
    }
}
