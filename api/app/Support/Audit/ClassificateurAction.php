<?php

namespace App\Support\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Déduit, du nom de route et de la méthode HTTP, quel type d'action une
 * requête représente et quel module elle touche — sans avoir à annoter
 * chacune des centaines de routes de l'API.
 *
 * Convention exploitée : `api.v1.<module>.<verbe>` (ex.
 * `api.v1.eleves.batch-delete`, `api.v1.personnels.fiche-pdf`).
 */
class ClassificateurAction
{
    private const ROUTES_SPECIALES = [
        'api.v1.auth.login' => 'connexion',
        'api.v1.desktop.connexion' => 'connexion',
        'api.v1.auth.logout' => 'deconnexion',
        'api.v1.sync.push' => 'synchronisation',
        'api.v1.desktop.synchroniser' => 'synchronisation',
        'api.v1.desktop.provisionner' => 'synchronisation',
    ];

    public static function action(Request $request): string
    {
        $route = $request->route()?->getName() ?? '';

        if (isset(self::ROUTES_SPECIALES[$route])) {
            return self::ROUTES_SPECIALES[$route];
        }

        $verbe = Str::lower(Str::afterLast($route, '.'));
        $methode = $request->method();

        if ($methode === 'GET' || $methode === 'HEAD') {
            return match (true) {
                (bool) preg_match('/export|excel|word|csv|modele|telecharg|download/', $verbe) => 'export',
                (bool) preg_match('/pdf|imprim|impression|attestation|recu|carte/', $verbe) => 'impression',
                default => 'consultation',
            };
        }

        if ($methode === 'DELETE') {
            return 'suppression';
        }

        if ($methode === 'PUT' || $methode === 'PATCH') {
            return 'modification';
        }

        return match (true) {
            (bool) preg_match('/import/', $verbe) => 'import',
            (bool) preg_match('/delete|destroy|supprim/', $verbe) => 'suppression',
            (bool) preg_match('/update|modifier/', $verbe) => 'modification',
            (bool) preg_match('/^(store|create|creer|ajouter)/', $verbe) => 'creation',
            (bool) preg_match('/export|pdf|excel|word/', $verbe) => 'export',
            default => 'action',
        };
    }

    public static function module(Request $request): ?string
    {
        $route = $request->route()?->getName();

        if ($route && str_starts_with($route, 'api.v1.')) {
            $segments = explode('.', Str::after($route, 'api.v1.'));

            return $segments[0] ?? null;
        }

        // Route sans nom : premier segment du chemin après `/api/v1/`.
        $segment = Str::of($request->path())->after('v1/')->before('/')->toString();

        return $segment !== '' ? $segment : null;
    }
}
