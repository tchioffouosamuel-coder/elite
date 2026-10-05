<?php

namespace App\Providers;

use App\Services\Paie\Bareme;
use App\Services\Paie\BaremeMaison;
use App\Services\Paie\BaremePaie;

use App\Models\User;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use App\Observers\ContactsTuteurObserver;
use App\Observers\TombstoneObserver;
use App\Support\Audit\CollecteurChangements;
use App\Support\Sync\RegistreSync;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(Bareme::class, fn() => config('paie.bareme') === 'legal'
            ? new BaremePaie
            : new BaremeMaison);

        // Une seule instance par processus : la pile qu'elle tient suit les
        // requêtes (et sous-requêtes de synchronisation) en cours.
        $this->app->singleton(CollecteurChangements::class);
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
        Tuteur::observe(ContactsTuteurObserver::class);
        TuteurTelephone::observe(ContactsTuteurObserver::class);

        /*
         * Journal d'audit : chaque écriture Eloquent faite pendant une requête
         * journalisée y est rattachée avec ses valeurs avant/après (cf.
         * `JournaliserAudit`). Hors requête (commande, tâche planifiée), le
         * collecteur est inactif et ignore l'événement.
         */
        foreach (['created', 'updated', 'deleted'] as $evenement) {
            Event::listen("eloquent.{$evenement}: *", function (string $nom, array $donnees) use ($evenement) {
                if (($donnees[0] ?? null) instanceof Model) {
                    app(CollecteurChangements::class)->enregistrer($evenement, $donnees[0]);
                }
            });
        }

        /*
         * L'API n'authentifie qu'en Bearer token (Sanctum) mais Scramble ne le
         * devine pas tout seul : sans schéma de sécurité déclaré, sa doc
         * interactive n'affiche aucun champ pour saisir un token, et « Send
         * API Request » part donc sans en-tête Authorization — 401 garanti
         * même avec des identifiants valides.
         */
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });

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
