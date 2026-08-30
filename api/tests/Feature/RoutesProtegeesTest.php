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
     * Exceptions admises : des routes qui n'agissent que sur l'appelant
     * lui-même, ou qui portent leur propre contrôle. Aucune ne peut dépendre
     * d'un privilège — exiger « profil.modifier » pour changer son propre nom
     * reviendrait à pouvoir en priver quelqu'un de son propre compte.
     *
     * @var list<string>
     */
    private const EXEMPTES = [
        // L'authentification elle-même. `mot-de-passe` est même la seule route
        // ouverte à un compte dont le mot de passe est encore provisoire.
        'api.v1.auth.login',
        'api.v1.auth.me',
        'api.v1.auth.refresh',
        'api.v1.auth.logout',
        'api.v1.auth.mot-de-passe',
        'api.v1.auth.profil',

        // Ses propres notifications : les lire ou les marquer lues ne touche
        // que les lignes déjà destinées à l'appelant.
        'api.v1.notifications.index',
        'api.v1.notifications.non-lues',
        'api.v1.notifications.lire',
        'api.v1.notifications.tout-lire',

        // Synchronisation mobile : l'endpoint filtre lui-même chaque entité
        // sur le privilège correspondant (cf. `RegistreSync`). Un privilège
        // unique en amont serait soit trop large, soit redondant.
        'api.v1.sync.pull',
        // Le push rejoue les routes métier une par une : chaque opération
        // repasse par le privilège de sa propre route.
        'api.v1.sync.push',

        // Enregistrement de son propre appareil pour les notifications push.
        'api.v1.appareils.store',
        'api.v1.appareils.destroy',

        // Vérification publique d'authenticité d'un bulletin : destinée à un
        // tiers extérieur à l'établissement, elle est protégée par la
        // signature HMAC portée dans l'URL, pas par un compte.
        'api.v1.verification-bulletin.show',
        // Même principe pour le reçu de versement, scanné depuis le papier.
        'api.v1.verification-versement.show',

        // Responsabilités du compte connecté (professeur principal,
        // surveillant général, censeur…) : il n'y lit que les siennes, et les
        // exiger derrière un privilège fermerait l'écran à l'agent qui vient
        // justement d'en recevoir une.
        'api.v1.classes.mes-attributions',

        // Espace personnel : ses propres avances sur salaire, et la demande
        // d'une nouvelle. Le contrôleur borne tout à la fiche du compte
        // connecté (cf. PersonnelEspaceController::moi) ; derrière
        // `finance.paie`, l'écran serait fermé à l'employé qu'il concerne.
        'api.v1.mon-espace.avances.index',
        'api.v1.mon-espace.avances.demandes.store',
        // Même principe pour le budget alloué : le contrôleur borne tout au
        // budget du personnel connecté (cf. PersonnelEspaceController::monBudget).
        'api.v1.mon-espace.budgets.index',
        'api.v1.mon-espace.budgets.note-gestion',
        'api.v1.mon-espace.budgets.bilan-pdf',

        // Portail parent : gardé par le rôle `role:parent` (pas un privilège
        // `X.view`) et borné aux seuls enfants du compte par ParentAccess.
        'api.v1.parent.enfants.index',
        'api.v1.parent.enfants.show',
        'api.v1.parent.enfants.finance',
        'api.v1.parent.enfants.bulletin',
        'api.v1.parent.enfants.progression',
        'api.v1.parent.enfants.progression.show',
        'api.v1.parent.enfants.absences',
        'api.v1.parent.enfants.emploi-du-temps',
        'api.v1.parent.enfants.visites-infirmerie',
        'api.v1.parent.enfants.sanctions',
        'api.v1.parent.enfants.justifications.index',
        'api.v1.parent.enfants.justifications.store',
        'api.v1.parent.enfants.observations.index',
        'api.v1.parent.enfants.observations.store',
        'api.v1.parent.enfants.modification.show',
        'api.v1.parent.enfants.modification.store',
        'api.v1.parent.enfants.modifications.index',
        'api.v1.parent.preinscriptions.index',
        'api.v1.parent.preinscriptions.store',
        'api.v1.parent.preinscriptions.show',
        'api.v1.parent.preinscriptions.update',
        'api.v1.parent.ecoles-disponibles',
        'api.v1.parent.ecoles.classes',

        // Espace enseignant : même principe que « mon-espace » ci-dessus (cf.
        // EnseignantController), sans middleware `permission`/`role` — le
        // périmètre (fiche personnel, département/niveau/classe dirigés) est
        // vérifié dans le contrôleur, avec un 403 explicite hors attribution.
        'api.v1.enseignant.mes-informations.show',
        'api.v1.enseignant.mes-informations.update',
        'api.v1.enseignant.remuneration.show',
        'api.v1.enseignant.mon-departement.show',
        'api.v1.enseignant.ma-classe-prof-principal.show',
        'api.v1.enseignant.mon-niveau.show',
        'api.v1.enseignant.evaluations.store',
    ];

    /**
     * Routes volontairement accessibles sans authentification. Leur contrôle
     * d'accès ne repose pas sur un compte mais sur le secret contenu dans
     * l'URL elle-même.
     *
     * @var list<string>
     */
    private const PUBLIQUES = [
        'api.v1.auth.login',
        'api.v1.verification-bulletin.show',
        'api.v1.verification-versement.show',
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
            if (! str_starts_with($route->uri(), 'api/v1/') || in_array($route->getName(), self::PUBLIQUES, true)) {
                continue;
            }

            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $ouvertes[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $ouvertes, "Routes accessibles sans authentification :\n".implode("\n", $ouvertes));
    }
}
