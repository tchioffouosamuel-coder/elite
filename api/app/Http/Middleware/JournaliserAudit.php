<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Assainisseur;
use App\Support\Audit\ClassificateurAction;
use App\Support\Audit\CollecteurChangements;
use App\Support\Telephone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journalise chaque requête API — connexion, consultation, création,
 * modification, suppression, export… — avec son auteur, l'heure, l'adresse
 * IP, le résultat et, pour les écritures, le détail avant/après de chaque
 * enregistrement touché (cf. {@see CollecteurChangements}).
 *
 * Écrit dans `handle()` et non dans `terminate()` : les opérations rejouées
 * par la synchronisation passent par `app()->handle()`, qui n'appelle jamais
 * `terminate()` — elles échapperaient sinon au journal.
 *
 * Une panne du journal ne doit jamais faire échouer l'action de
 * l'utilisateur : toute erreur d'écriture est signalée puis ignorée.
 */
class JournaliserAudit
{
    public function __construct(private readonly CollecteurChangements $collecteur) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('audit.actif') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $debut = microtime(true);
        $this->collecteur->ouvrir();

        try {
            $reponse = $next($request);
        } finally {
            $changements = $this->collecteur->fermer();
        }

        try {
            if (! $this->estExclue($request)) {
                $this->journaliser($request, $reponse, $changements, $debut);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $reponse;
    }

    private function estExclue(Request $request): bool
    {
        $route = $request->route()?->getName();

        if ($route === null) {
            return false;
        }

        if (in_array($route, config('audit.routes_exclues', []), true)) {
            return true;
        }

        return $request->boolean('actualisation_auto')
            && in_array($route, config('audit.routes_actualisation_auto', []), true);
    }

    /** @param  list<array<string, mixed>>  $changements */
    private function journaliser(Request $request, Response $reponse, array $changements, float $debut): void
    {
        $action = ClassificateurAction::action($request);
        $statut = $reponse->getStatusCode();

        $user = $request->user();

        if ($action === 'connexion') {
            $user = $this->utilisateurDeLaConnexion($request, $reponse);

            if ($statut >= 400) {
                $action = 'connexion_echouee';
            }
        }

        $schoolId = app()->bound('tenant.school_id') ? app('tenant.school_id') : null;

        AuditLog::create([
            'created_at' => now(),
            'school_id' => $schoolId ?? $user?->school_id,
            'user_id' => $user?->id,
            'user_nom' => $user?->name ?? $this->identifiantSaisi($request),
            'user_role' => $user ? $this->libelleRole($user) : null,
            'action' => $action,
            'module' => ClassificateurAction::module($request),
            'route' => $request->route()?->getName(),
            'methode' => $request->method(),
            'url' => mb_substr($request->getRequestUri(), 0, 2048),
            'parametres' => $request->route()?->parameters() ?: null,
            'donnees' => $this->donnees($request),
            'changements' => $changements ?: null,
            'statut_http' => $statut,
            'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
        ]);
    }

    /**
     * Corps de la requête, hors paramètres d'URL (déjà visibles dans `url`) :
     * sans intérêt pour une consultation, essentiel pour une écriture.
     *
     * @return array<string, mixed>|null
     */
    private function donnees(Request $request): ?array
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return null;
        }

        $corps = array_merge($request->except(array_keys($request->query())), $request->allFiles());

        return $corps === [] ? null : Assainisseur::donnees($corps);
    }

    /**
     * À la connexion, personne n'est encore authentifié : on retrouve le
     * compte visé — par la réponse en cas de succès, par l'identifiant
     * saisi sinon, pour qu'une série d'échecs sur un même compte se voie.
     */
    private function utilisateurDeLaConnexion(Request $request, Response $reponse): ?User
    {
        $contenu = json_decode((string) $reponse->getContent(), true);
        $id = $contenu['data']['user']['id'] ?? null;

        if ($id) {
            return User::find($id);
        }

        $identifiant = $this->identifiantSaisi($request);

        if ($identifiant === null) {
            return null;
        }

        return str_contains($identifiant, '@')
            ? User::where('email', $identifiant)->first()
            : User::where('phone', Telephone::normaliser($identifiant))->first()
                ?? User::whereHas('eleve', fn ($q) => $q->where('matricule', $identifiant))->first();
    }

    private function identifiantSaisi(Request $request): ?string
    {
        $identifiant = trim((string) $request->input('identifiant', $request->input('email', '')));

        return $identifiant !== '' ? mb_substr($identifiant, 0, 191) : null;
    }

    private function libelleRole(User $user): ?string
    {
        try {
            return $user->libelleRole();
        } catch (\Throwable) {
            return null;
        }
    }
}
