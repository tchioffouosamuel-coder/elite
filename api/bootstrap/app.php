<?php

use App\Console\Commands\EnvoyerRapportHebdomadaireParents;
use App\Console\Commands\RappelEcheancesCommand;
use App\Helpers\ApiResponse;
use App\Http\Middleware\ExigerMotDePasseRenouvele;
use App\Http\Middleware\Idempotence;
use App\Http\Middleware\ScopeEtablissement;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifierPermission;
use App\Http\Middleware\VerifierSuperAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Samedi soir : la semaine de cours (lundi-vendredi) est terminée,
        // le résumé porte sur une semaine complète plutôt que sur un
        // découpage arbitraire à cheval sur deux semaines scolaires.
        $schedule->command(EnvoyerRapportHebdomadaireParents::class)->weeklyOn(6, '18:00');

        // Chaque matin, avant l'ouverture du guichet : le personnel finance a
        // la journée pour relancer les familles dont l'échéance approche.
        $schedule->command(RappelEcheancesCommand::class)->dailyAt('07:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Render (comme tout hébergeur derrière un load balancer) termine le
        // TLS puis nous parle en HTTP interne : sans ceci, Laravel croit
        // chaque requête non chiffrée et génère des URLs en http:// (liens
        // de fichiers, cookies Sanctum sans l'attribut Secure).
        $middleware->trustProxies(at: '*');

        // API pure, sans route web "login" : ne jamais tenter de rediriger un
        // invité, toujours laisser l'exception JSON standard (401) répondre.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->alias([
            // Notre middleware plutôt que celui de spatie : lui seul connaît les
            // privilèges hérités de la fonction et rend un 403 nommant le
            // privilège manquant (cf. VerifierPermission).
            'permission' => VerifierPermission::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'super_admin' => VerifierSuperAdmin::class,
            'mot_de_passe' => ExigerMotDePasseRenouvele::class,
            'tenant' => ScopeEtablissement::class,
            // Sans en-tête `Idempotency-Key` il se retire de lui-même : il ne
            // coûte donc rien aux requêtes du web, qui n'en envoient pas.
            'idempotence' => Idempotence::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::validationError($e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::unauthorized();
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::notFound();
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'Erreur.', $e->getStatusCode());
            }
        });

        /*
         * Filet de sécurité : toute exception non prévue ci-dessus (une
         * contrainte SQL non validée en amont, par exemple) ne doit jamais
         * atteindre le client avec sa requête brute, ses valeurs liées et les
         * identifiants de connexion à la base — cf. l'incident où un libellé
         * d'année scolaire en doublon a renvoyé jusqu'à l'hôte et au port
         * MySQL dans le message d'erreur affiché au guichet.
         *
         * `return null` en debug laisse Laravel afficher le détail complet
         * (utile en local) ; ce renderer ne s'applique donc qu'en production,
         * où `APP_DEBUG` est à `false`.
         */
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            return ApiResponse::error(
                "Une erreur inattendue est survenue. Veuillez réessayer, ou contacter le support si le problème persiste.",
                500,
            );
        });
    })->create();