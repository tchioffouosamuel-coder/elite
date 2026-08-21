<?php

namespace App\Providers;

use App\Services\Paie\Bareme;
use App\Services\Paie\BaremeMaison;
use App\Services\Paie\BaremePaie;

use App\Models\User;
use App\Observers\TombstoneObserver;
use App\Support\Sync\RegistreSync;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Le barème de paie se choisit par configuration : l'établissement
         * applique celui de ses registres, mais le barème légal doit rester
         * accessible sans toucher au code — c'est le même calcul qui répond
         * devant la CNPS et le fisc.
         */
        $this->app->bind(Bareme::class, fn () => config('paie.bareme') === 'legal'
            ? new BaremePaie
            : new BaremeMaison);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Chaque entité répliquée sur mobile signale ses suppressions, faute de
         * quoi un téléphone hors-ligne garderait indéfiniment des lignes
         * effacées côté serveur (cf. `TombstoneObserver`).
         */
        foreach (RegistreSync::entites() as $definition) {
            $definition['modele']::observe(TombstoneObserver::class);
        }

        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if ($user->estSuperAdmin()) {
                return true;
            }

            /*
             * Un privilège hérité de la fonction doit ouvrir exactement les
             * mêmes portes qu'un privilège porté par le rôle, sinon `can()`
             * dans le code et le middleware `permission` diverge.
             *
             * On ne renvoie jamais `false` : rendre la main (null) laisse
             * spatie évaluer les attributions directes et les rôles, puis les
             * policies faire leur travail.
             */
            return $user->fonction()?->codesPermissions()->contains($ability) ? true : null;
        });
    }
}
