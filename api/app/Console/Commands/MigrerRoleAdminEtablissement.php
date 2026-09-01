<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bascule les derniers titulaires du rôle `admin_etablissement` (aboli au
 * profit de deux rôles distincts) vers `admin_ecole` ou `admin_college`,
 * selon le type de l'établissement auquel ils sont rattachés.
 *
 * Idempotente : un utilisateur déjà migré n'a plus `admin_etablissement`,
 * donc une seconde exécution ne trouve plus rien à faire. Rejoue le
 * `hasAnyRole` de `User::estPersonnelDirection()` pour ne perdre aucun
 * accès de direction pendant la bascule.
 */
class MigrerRoleAdminEtablissement extends Command
{
    protected $signature = 'roles:migrer-admin-etablissement';

    protected $description = "Réaffecte les titulaires du rôle admin_etablissement vers admin_ecole ou admin_college selon le type de leur école.";

    public function handle(): int
    {
        $utilisateurs = User::role('admin_etablissement')->with('school')->get();

        if ($utilisateurs->isEmpty()) {
            $this->info('Aucun titulaire de admin_etablissement à migrer.');

            return self::SUCCESS;
        }

        $migres = 0;
        $ignores = 0;

        foreach ($utilisateurs as $utilisateur) {
            if (! $utilisateur->school) {
                $this->warn("Utilisateur #{$utilisateur->id} ({$utilisateur->email}) sans école rattachée — ignoré, à revoir manuellement.");
                $ignores++;

                continue;
            }

            $roleCible = $utilisateur->school->estSecondaire() ? 'admin_college' : 'admin_ecole';

            $utilisateur->removeRole('admin_etablissement');
            $utilisateur->assignRole($roleCible);

            $this->line("#{$utilisateur->id} ({$utilisateur->email}) -> {$roleCible}");
            $migres++;
        }

        $this->info("{$migres} utilisateur(s) migré(s), {$ignores} ignoré(s).");

        return self::SUCCESS;
    }
}
