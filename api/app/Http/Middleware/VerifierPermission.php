<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Support\CataloguePermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
 * Quand la route porte sur une classe, le privilège ne suffit pas : il faut
 * encore qu'il vaille **pour cette classe-là**. Un surveillant général tient
 * la discipline des classes qui lui sont assignées, pas de l'établissement
 * entier ; un enseignant note les siennes. C'est ici que le périmètre
 * s'applique, une fois pour toutes les routes plutôt que contrôleur par
 * contrôleur — cf. App\Support\Perimetre.
 *
 * Usage : `->middleware('permission:eleves.update')`, ou plusieurs privilèges
 * séparés par `|` quand l'action les exige tous. Pour les rares endpoints
 * partagés entre plusieurs parcours, ajouter `,any`.
 */
class VerifierPermission
{
    public function handle(Request $request, Closure $next, string $permissions, string $mode = 'all'): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        $requises = array_values(array_filter(explode('|', $permissions)));
        $classeId = $this->classeConcernee($request->route());
        $departementId = $classeId === null ? $this->departementConcerne($request->route()) : null;
        $modeAny = $mode === 'any';
        $refusees = [];

        foreach ($requises as $permission) {
            $accorde = match (true) {
                $classeId !== null => $user->peutSurClasse($permission, $classeId),
                $departementId !== null => $user->peutSurDepartement($permission, $departementId),
                default => $user->aLaPermission($permission),
            };

            if ($modeAny && $accorde) {
                return $next($request);
            }

            if (! $accorde) {
                $refusees[] = $permission;
            }
        }

        if (! $modeAny && $refusees === []) {
            return $next($request);
        }

        // Détenir le privilège mais pas sur cette classe n'est pas la même
        // chose que ne pas l'avoir : dire « il vous manque “Consulter les
        // classes” » à un enseignant qui consulte les siennes tous les jours
        // l'enverrait réclamer un droit qu'il a déjà.
        if ($classeId !== null && $this->detientLesPrivilegesAttendus($user, $requises, $modeAny)) {
            return ApiResponse::forbidden(
                "Cette classe n'entre pas dans votre périmètre : vous n'y enseignez pas et elle ne vous a pas été confiée.",
                $requises,
            );
        }

        if ($departementId !== null && $this->detientLesPrivilegesAttendus($user, $requises, $modeAny)) {
            return ApiResponse::forbidden(
                "Ce département n'entre pas dans votre périmètre : vous n'en êtes pas le chef.",
                $requises,
            );
        }

        $manquantes = $modeAny ? $requises : $refusees;

        return ApiResponse::forbidden($this->message($manquantes, $modeAny), $manquantes);
    }

    /**
     * Classe visée par la route, quand il y en a une. Deux formes cohabitent
     * dans `routes/api.php` : le paramètre explicite `{classeId}` des routes
     * rattachées à une classe (absences, notes, emploi du temps…) et le
     * `{id}` des routes de la ressource `classes` elle-même.
     */
    private function classeConcernee(?Route $route): ?int
    {
        if (! $route) {
            return null;
        }

        $id = $route->parameter('classeId')
            ?? (str_contains($route->uri(), 'classes/{id}') ? $route->parameter('id') : null);

        return is_numeric($id) ? (int) $id : null;
    }

    /** Département visé par la route, pour la fiche et ses statistiques. */
    private function departementConcerne(?Route $route): ?int
    {
        if (! $route || ! str_contains($route->uri(), 'departements/{id}')) {
            return null;
        }

        $id = $route->parameter('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  list<string>  $requises
     */
    private function detientUnDesPrivileges(mixed $user, array $requises): bool
    {
        foreach ($requises as $permission) {
            if ($user->aLaPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $requises
     */
    private function detientTousLesPrivileges(mixed $user, array $requises): bool
    {
        foreach ($requises as $permission) {
            if (! $user->aLaPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $requises
     */
    private function detientLesPrivilegesAttendus(mixed $user, array $requises, bool $modeAny): bool
    {
        return $modeAny
            ? $this->detientUnDesPrivileges($user, $requises)
            : $this->detientTousLesPrivileges($user, $requises);
    }

    /**
     * @param  list<string>  $requises
     */
    private function message(array $requises, bool $modeAny): string
    {
        $libelles = array_map(fn (string $code) => '« '.CataloguePermissions::libelle($code).' »', $requises);

        if (count($libelles) === 1) {
            return 'Privilège requis pour cette action : '.$libelles[0].'.';
        }

        return $modeAny
            ? "Cette action demande l'un de ces privilèges : ".implode(', ', $libelles).'.'
            : 'Cette action demande tous ces privilèges : '.implode(', ', $libelles).'.';
    }
}
