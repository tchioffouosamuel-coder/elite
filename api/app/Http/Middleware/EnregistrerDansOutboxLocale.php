<?php

namespace App\Http\Middleware;

use App\Models\SyncOutbox;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sur une instance locale (client desktop, `SYNC_LOCAL_REPLICA=true`),
 * enregistre chaque écriture réussie pour rejeu ultérieur vers le serveur
 * distant — voir {@see \App\Console\Commands\SyncPush}.
 *
 * Sans effet sur le serveur distant lui-même : `config('sync.local_replica')`
 * y reste faux, donc ce middleware ne coûte rien à la production actuelle.
 *
 * Le format enregistré (`methode`, `chemin`, `corps`) est volontairement
 * identique à celui qu'attend déjà {@see \App\Http\Controllers\Api\V1\SyncController::push()}
 * côté serveur distant : aucune conversion n'est nécessaire au moment du
 * rejeu, l'outbox locale ET l'outbox mobile alimentent le même endpoint.
 */
class EnregistrerDansOutboxLocale
{
    /** @var list<string> */
    private const METHODES_MUTANTES = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        if (! config('sync.local_replica')
            || ! in_array($request->method(), self::METHODES_MUTANTES, true)
            || ! $reponse->isSuccessful()
            || $request->routeIs(['api.v1.sync.*', 'api.v1.auth.*', 'api.v1.desktop.*'])) {
            return $reponse;
        }

        SyncOutbox::create([
            'id' => (string) Str::uuid(),
            'methode' => $request->method(),
            // Chemin relatif à `/api/v1/`, tel qu'attendu par
            // `SyncController::rejouer()` côté serveur distant.
            'chemin' => Str::after($request->path(), 'v1/'),
            'corps' => $request->all(),
        ]);

        return $reponse;
    }
}
