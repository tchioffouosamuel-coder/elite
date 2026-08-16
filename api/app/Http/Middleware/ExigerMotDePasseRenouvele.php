<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme l'application tant que le mot de passe provisoire n'a pas été remplacé.
 *
 * La redirection côté interface ne suffirait pas : le jeton délivré à la
 * connexion vaut pour toute l'API, et il suffirait d'appeler les routes
 * directement pour travailler sous le mot de passe commun à l'établissement.
 * Le refus est donc prononcé ici, sur toutes les routes sauf celles qui
 * permettent précisément de sortir de cet état.
 */
class ExigerMotDePasseRenouvele
{
    /**
     * Consulter son profil, changer son mot de passe et se déconnecter restent
     * possibles — sans quoi l'agent serait enfermé dehors.
     *
     * @var list<string>
     */
    private const ROUTES_LIBRES = [
        'api.v1.auth.me',
        'api.v1.auth.mot-de-passe',
        'api.v1.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->doit_changer_mot_de_passe && ! in_array($request->route()?->getName(), self::ROUTES_LIBRES, true)) {
            return ApiResponse::error(
                'Vous devez définir un nouveau mot de passe avant d\'utiliser l\'application.',
                423,
                ['mot_de_passe_a_renouveler' => true],
            );
        }

        return $next($request);
    }
}
