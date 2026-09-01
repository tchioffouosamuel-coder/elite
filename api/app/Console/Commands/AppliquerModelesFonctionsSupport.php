<?php

namespace App\Console\Commands;

use App\Models\FonctionReferentiel;
use App\Support\FonctionRoles;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applique le nouveau gabarit de permissions (infirmier, chauffeur, agent de
 * sécurité, agent d'entretien) aux fonctions du référentiel déjà en place
 * dans les établissements existants, qui avaient été seedées vides à
 * l'époque où `FonctionRoles::CORRESPONDANCES` renvoyait `null` pour ces
 * libellés.
 *
 * La migration 2026_09_01_150000 exécute déjà ce même geste automatiquement
 * lors du déploiement (`php artisan migrate --force`) : cette commande ne
 * sert plus qu'à un rejeu manuel ponctuel (ex. une fonction réinitialisée
 * par erreur).
 *
 * Même règle de prudence que `FonctionPermissionSeeder` : ne touche jamais
 * une fonction déjà personnalisée (permissions_count > 0), pour ne jamais
 * écraser un réglage fait à la main par un super admin.
 */
class AppliquerModelesFonctionsSupport extends Command
{
    private const ROLES_CIBLES = ['infirmier', 'chauffeur', 'agent_securite', 'agent_entretien'];

    protected $signature = 'fonctions:appliquer-modeles-support';

    protected $description = "Applique les gabarits de permissions du personnel de soutien (infirmier, chauffeur, agents de sécurité/entretien) aux fonctions encore vierges.";

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $idsParCode = Permission::where('guard_name', 'web')->pluck('id', 'name');
        $appliques = 0;

        FonctionReferentiel::query()
            ->withCount('permissions')
            ->orderBy('id')
            ->get()
            ->each(function (FonctionReferentiel $fonction) use ($idsParCode, &$appliques) {
                if ($fonction->permissions_count > 0) {
                    return;
                }

                $role = FonctionRoles::role($fonction->label_fr);

                if (! in_array($role, self::ROLES_CIBLES, true)) {
                    return;
                }

                $codes = RolePermissionSeeder::ROLE_PERMISSIONS[$role] ?? [];
                $fonction->permissions()->sync($idsParCode->only($codes)->values());

                $this->line("Fonction #{$fonction->id} ({$fonction->label_fr}) -> gabarit {$role}");
                $appliques++;
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("{$appliques} fonction(s) mise(s) à jour.");

        return self::SUCCESS;
    }
}
