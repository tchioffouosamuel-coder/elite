<?php

namespace App\Http\Middleware;

use App\Models\SyncOutbox;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            // École réellement visée par CETTE écriture (résolue par
            // `ScopeEtablissement` plus haut dans le pipeline, déjà exécuté
            // puisqu'on est après `$next($request)`) — sans elle, le rejeu
            // distant n'aurait que le contexte, sans rapport, de l'appel
            // `/api/v1/sync` qui le transporte (cf. migration
            // `add_school_id_to_sync_outbox_table`).
            'school_id' => app('tenant.school_id'),
            'corps' => $this->corpsAvecFichiers($request),
        ]);

        return $reponse;
    }

    /**
     * `$request->all()` fusionne les fichiers uploadés (`UploadedFile`) dans
     * le tableau — cast en JSON par `SyncOutbox::corps` (colonne `array`),
     * un `UploadedFile` s'y sérialise en objet vide : le fichier disparaît
     * silencieusement. On le remplace ici par son contenu encodé en base64,
     * seule façon de le faire traverser l'aller-retour JSON jusqu'au rejeu
     * sur le serveur distant (cf. `SyncController::extraireFichiers()`).
     *
     * @return array<string, mixed>
     */
    private function corpsAvecFichiers(Request $request): array
    {
        $corps = $request->all();

        foreach ($request->allFiles() as $champ => $fichier) {
            if ($fichier instanceof UploadedFile) {
                $corps[$champ] = $this->encoderFichier($fichier);
            } elseif (is_array($fichier) && isset($corps[$champ]) && is_array($corps[$champ])) {
                // Champ de fichiers multiple (`photos[]`) : même niveau
                // d'imbrication que `allFiles()` le rapporte.
                foreach ($fichier as $sousChamp => $sousFichier) {
                    if ($sousFichier instanceof UploadedFile) {
                        $corps[$champ][$sousChamp] = $this->encoderFichier($sousFichier);
                    }
                }
            }
        }

        return $corps;
    }

    /** @return array{__sync_fichier__: true, nom: string, mime: ?string, contenu_base64: string} */
    private function encoderFichier(UploadedFile $fichier): array
    {
        return [
            '__sync_fichier__' => true,
            'nom' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getMimeType(),
            'contenu_base64' => base64_encode($fichier->get()),
        ];
    }
}
