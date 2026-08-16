<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Garde-fou : toute action de l'API doit contrôler les droits de son appelant.
 *
 * Une route ajoutée sans `permission:` ni `super_admin` serait ouverte à tout
 * compte connecté, quel que soit son métier — et le trou passerait inaperçu,
 * l'interface se contentant de masquer le bouton correspondant. Ce test le
 * fait échouer à l'ajout plutôt qu'à l'incident.
 */
class RoutesProtegeesTest extends TestCase
{
    /**
     * Seules exceptions admises : l'authentification elle-même, la lecture du
     * profil courant et le renouvellement de son propre mot de passe. Aucune ne
     * peut dépendre d'un privilège — la dernière est même la seule route
     * ouverte à un compte dont le mot de passe est encore provisoire.
     *
     * @var list<string>
     */
    private const EXEMPTES = [
        'api.v1.auth.login',
        'api.v1.auth.me',
        'api.v1.auth.refresh',
        'api.v1.auth.logout',
        'api.v1.auth.mot-de-passe',
    ];

    public function test_toute_route_api_controle_les_privileges(): void
    {
        $nues = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || in_array($route->getName(), self::EXEMPTES, true)) {
                continue;
            }

            $middlewares = $route->gatherMiddleware();

            $controlee = collect($middlewares)->contains(
                fn ($m) => is_string($m) && (str_starts_with($m, 'permission:') || $m === 'super_admin'),
            );

            if (! $controlee) {
                $nues[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $nues, "Routes sans contrôle de privilège :\n".implode("\n", $nues));
    }

    public function test_toute_route_api_exige_une_authentification(): void
    {
        $ouvertes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || $route->getName() === 'api.v1.auth.login') {
                continue;
            }

            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $ouvertes[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $ouvertes, "Routes accessibles sans authentification :\n".implode("\n", $ouvertes));
    }
}
