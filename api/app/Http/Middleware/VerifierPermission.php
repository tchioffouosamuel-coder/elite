<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Support\CataloguePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'autorisation de toutes les routes métier — remplace le middleware
 * de spatie sous le même alias `permission`.
 *
 * Deux raisons de ne pas garder celui de la bibliothèque : il ignore les
 * privilèges hérités de la fonction (il interroge le rôle et les attributions
 * directes), et il lève une `UnauthorizedException` dont le message anglais
 * (« User does not have the right permissions ») finit tel quel dans l'alerte
 * affichée à l'utilisateur. Ici la réponse nomme le privilège manquant, en
 * clair, pour que l'interface puisse dire quoi demander à l'administrateur.
 *
 * Usage : `->middleware('permission:eleves.manage')`, ou plusieurs privilèges
 * séparés par `|` — il suffit d'en détenir un.
 */
class VerifierPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        $requises = array_values(array_filter(explode('|', $permissions)));

        foreach ($requises as $permission) {
            if ($user->aLaPermission($permission)) {
                return $next($request);
            }
        }

        return ApiResponse::forbidden($this->message($requises), $requises);
    }

    /**
     * @param  list<string>  $requises
     */
    private function message(array $requises): string
    {
        $libelles = array_map(fn (string $code) => '« '.CataloguePermissions::libelle($code).' »', $requises);

        return count($libelles) === 1
            ? 'Privilège requis pour cette action : '.$libelles[0].'.'
            : "Cette action demande l'un de ces privilèges : ".implode(', ', $libelles).'.';
    }
}
