<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\School;
use App\Models\SyncOutbox;
use App\Models\User;
use App\Support\Sync\RegistreSync;
use App\Support\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Provisioning et pilotage de la synchronisation d'une instance Laravel
 * **locale** (client desktop offline, `SYNC_LOCAL_REPLICA=true`).
 *
 * N'a de sens que sur cette instance locale : le serveur distant n'expose
 * jamais ces routes en pratique (elles ne créeraient qu'un compte de plus
 * sur lui-même), mais rien ne les empêche techniquement — elles ne
 * dépendent que de la présence d'une ligne dans `desktop_provisioning`.
 */
class DesktopProvisioningController extends Controller
{
    /**
     * Lie ce poste à un compte, à partir des jetons obtenus par une
     * connexion réussie sur le serveur distant (l'écran de connexion
     * appelle cet endpoint la première fois qu'un compte donné se connecte
     * sur CE poste).
     *
     * Multi-utilisateur : plusieurs comptes peuvent être provisionnés sur le
     * même poste (ex. plusieurs membres du personnel qui se relaient sur le
     * même ordinateur de l'école) — seul un second provisioning du MÊME
     * compte est refusé, pas celui d'un compte différent.
     */
    public function provisionner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serveur_url' => ['required', 'url'],
            'token' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
            // Le mot de passe qui vient de servir à la connexion sur le
            // serveur distant (formulaire de connexion desktop) : jamais
            // renvoyé ni renvoyé en clair ensuite, seul son hash est stocké
            // (colonne `password`) pour permettre à CE compte de rouvrir sa
            // session localement (cf. connexion()), y compris hors-ligne.
            'password' => ['required', 'string'],
            'user' => ['required', 'array'],
            'user.id' => ['required', 'integer'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['nullable', 'email', 'max:255'],
            'user.phone' => ['nullable', 'string', 'max:30'],
            'user.locale' => ['nullable', 'string', 'max:5'],
            'user.is_active' => ['nullable', 'boolean'],
            'user.school_id' => ['nullable', 'integer'],
            'user.roles' => ['array'],
            'user.roles.*' => ['string'],
            'user.permissions' => ['array'],
            'user.permissions.*' => ['string'],
            // Toutes les écoles accessibles au compte (cf. `User::ecolesAccessibles()`
            // côté serveur distant) — un compte non borné à une seule école
            // (super admin d'un complexe) en réplique plusieurs sur ce poste.
            // Un compte normal n'en envoie jamais qu'une.
            'schools' => ['required', 'array', 'min:1'],
            'schools.*.id' => ['required', 'integer'],
            'schools.*.name' => ['required', 'string', 'max:255'],
            'schools.*.code' => ['required', 'string', 'max:50'],
            'schools.*.type' => ['required', 'string', 'max:50'],
        ]);

        // `id` n'est fillable ni sur `School` ni sur `User` : `updateOrCreate`
        // laisserait silencieusement l'auto-incrément attribuer un autre
        // identifiant que celui du serveur distant, brisant toute
        // correspondance avec les lignes reçues ensuite par `sync:pull`.
        // L'affectation directe (`->id =`) contourne le mass-assignment pour
        // cette seule colonne.
        $ecoles = collect($data['schools'])->map(function (array $donneesEcole) {
            $ecole = School::find($donneesEcole['id']) ?? new School();
            $ecole->id = $donneesEcole['id'];
            $ecole->fill([
                'name' => $donneesEcole['name'],
                'code' => $donneesEcole['code'],
                'type' => $donneesEcole['type'],
                'is_active' => true,
            ]);
            $ecole->save();

            return $ecole;
        });

        // École "principale" du compte, quand il en a une (un compte non
        // borné comme le super admin peut n'en avoir aucune en propre) :
        // uniquement parmi celles qu'on vient de répliquer, jamais une
        // valeur du serveur distant qui pointerait vers une école absente
        // du lot fourni.
        $schoolId = isset($data['user']['school_id']) && $ecoles->contains('id', $data['user']['school_id'])
            ? $data['user']['school_id']
            : null;

        if (DesktopProvisioning::pourUtilisateur($data['user']['id']) !== null) {
            return ApiResponse::error('Ce compte est déjà lié à ce poste.', 409);
        }

        $utilisateur = User::find($data['user']['id']) ?? new User();
        $utilisateur->id = $data['user']['id'];
        $utilisateur->fill([
            'name' => $data['user']['name'],
            'email' => $data['user']['email'] ?? null,
            'phone' => $data['user']['phone'] ?? null,
            'locale' => $data['user']['locale'] ?? 'fr',
            'is_active' => $data['user']['is_active'] ?? true,
            'school_id' => $schoolId,
        ]);
        // Mot de passe distant : cette copie locale ne le vérifie jamais
        // (cf. connexion(), qui vérifie `desktop_provisioning.password`,
        // propre à CE poste) — un compte sans mot de passe local exploitable
        // reste malgré tout impossible à atteindre depuis l'API distante,
        // qu'aucun poste desktop n'expose.
        $utilisateur->password = Hash::make(Str::random(40));
        // Explicite plutôt que laissé au défaut de colonne : si l'identifiant
        // remote coïncide avec celui du compte super-admin de démo seedé
        // localement par une migration (`create_super_admin_user`, id 1),
        // `User::find()` réutilise CETTE ligne, qui porte déjà
        // `doit_changer_mot_de_passe = true`. Sans ce reset, le renouvellement
        // exigerait de connaître un mot de passe local qui n'existe pas
        // vraiment — la politique de mot de passe reste l'affaire du serveur
        // distant, jamais de cette copie locale authentifiée sans mot de passe.
        $utilisateur->doit_changer_mot_de_passe = false;
        $utilisateur->save();

        foreach ($data['user']['roles'] ?? [] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $utilisateur->syncRoles($data['user']['roles'] ?? []);

        foreach ($data['user']['permissions'] ?? [] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $utilisateur->syncPermissions($data['user']['permissions'] ?? []);

        $provisioning = DesktopProvisioning::create([
            'user_id' => $utilisateur->id,
            'password' => Hash::make($data['password']),
            'serveur_url' => $data['serveur_url'],
            'token' => $data['token'],
            'refresh_token' => $data['refresh_token'],
            'provisionne_le' => now(),
        ]);

        foreach ($ecoles as $ecole) {
            DesktopProvisioningEcole::create([
                'desktop_provisioning_id' => $provisioning->id,
                'school_id' => $ecole->id,
            ]);
        }

        Artisan::call('sync:pull');

        return ApiResponse::created([
            'provisionne' => true,
            'utilisateur' => ['id' => $utilisateur->id, 'name' => $utilisateur->name],
            'pull' => trim(Artisan::output()),
        ], 'Poste lié avec succès.');
    }

    /**
     * Connexion locale : plusieurs comptes pouvant désormais partager le
     * même poste, chacun doit se réauthentifier explicitement avec son
     * propre mot de passe **local** (capturé une seule fois au provisioning,
     * cf. `provisionner()`) — plus d'ouverture automatique et silencieuse du
     * premier compte venu.
     *
     * Même résolution d'identifiant que `AuthService::login()` côté serveur
     * distant (e-mail si « @ », téléphone normalisé sinon), restreinte aux
     * seuls comptes déjà provisionnés sur CE poste.
     */
    public function connexion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifiant = trim($data['identifiant']);

        $utilisateur = str_contains($identifiant, '@')
            ? User::where('email', $identifiant)->first()
            : User::where('phone', Telephone::normaliser($identifiant))->first();

        $provisioning = $utilisateur ? DesktopProvisioning::pourUtilisateur($utilisateur->id) : null;

        if (! $provisioning || ! $provisioning->password || ! Hash::check($data['password'], $provisioning->password) || ! $utilisateur->is_active) {
            return ApiResponse::error('Identifiants incorrects, ou ce compte n’est pas lié à ce poste.', 401);
        }

        $jeton = $utilisateur->createToken('desktop-session', ['access']);

        return ApiResponse::success([
            'user' => $utilisateur,
            'token' => $jeton->plainTextToken,
        ]);
    }

    /**
     * `?verifier=1` déclenche en plus une vérification de complétude
     * (comparaison des comptages locaux à ceux du serveur distant, école par
     * école) — hors du chemin par défaut : c'est un aller-retour réseau par
     * école, à ne pas payer à chaque rafraîchissement d'un simple indicateur
     * de statut.
     */
    public function statutSync(Request $request): JsonResponse
    {
        $provisioning = DesktopProvisioning::pourUtilisateur($request->user()->id);

        if ($provisioning === null) {
            return ApiResponse::notFound('Ce poste n’est lié à aucun compte.');
        }

        $ecoles = $provisioning->ecoles()->with('school:id,name')->get();
        $verifier = $request->boolean('verifier');

        return ApiResponse::success([
            // Le plus ancien pull parmi les écoles du poste : celle qui
            // traîne le plus est celle qui dirait à l'utilisateur « ça n'a
            // pas encore tourné », pas la plus fraîche qui masquerait le
            // retard des autres.
            'dernier_pull_le' => $ecoles->pluck('dernier_pull_le')->filter()->min()?->toIso8601String(),
            'dernier_push_le' => $provisioning->dernier_push_le?->toIso8601String(),
            'en_attente_push' => SyncOutbox::query()->enAttente()->where('desktop_provisioning_id', $provisioning->id)->count(),
            'ecoles' => $ecoles->map(fn (DesktopProvisioningEcole $e) => [
                'school_id' => $e->school_id,
                'nom' => $e->school?->name,
                'dernier_pull_le' => $e->dernier_pull_le?->toIso8601String(),
                // `null` = non vérifié (verifier=0, ou aléa réseau pendant la
                // vérification) — à ne pas confondre avec `false` (incomplet
                // confirmé) : l'un dit « on ne sait pas », l'autre « il
                // manque des lignes ».
                'complet' => $verifier ? $this->completudeEcole($provisioning, $e->school_id) : null,
            ])->values(),
        ]);
    }

    /**
     * Vrai si chaque entité du registre compte, en local, au moins autant de
     * lignes que sur le serveur distant pour cette école — `null` si la
     * vérification elle-même a échoué (aléa réseau), pas une conclusion.
     *
     * « Au moins autant » plutôt que « exactement autant » : une écriture
     * faite hors-ligne et pas encore poussée (cf. SyncPush) est, à cet
     * instant précis, en avance sur le serveur distant — ce n'est pas un
     * signe d'incomplétude.
     */
    private function completudeEcole(DesktopProvisioning $provisioning, int $schoolId): ?bool
    {
        try {
            $reponse = Http::withToken($provisioning->token)
                ->withHeaders(['X-School-Id' => $schoolId])
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get('sync/comptage');

            if ($reponse->failed()) {
                return null;
            }

            $distant = (array) $reponse->json('data');
            $definitions = RegistreSync::entites($provisioning->user);

            foreach ($distant as $cle => $comptageDistant) {
                if (! isset($definitions[$cle])) {
                    continue;
                }

                $requete = $definitions[$cle]['modele']::query();
                ($definitions[$cle]['portee'])($requete, $schoolId);

                if ($requete->count() < $comptageDistant) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Déclenche un cycle pull puis push, pour un bouton « Synchroniser
     * maintenant ». Rejoue pour TOUS les comptes du poste (base locale
     * partagée) — la vérification ci-dessous ne fait que confirmer que le
     * compte qui clique est bien l'un d'eux.
     */
    public function synchroniser(Request $request): JsonResponse
    {
        if (DesktopProvisioning::pourUtilisateur($request->user()->id) === null) {
            return ApiResponse::notFound('Ce poste n’est lié à aucun compte.');
        }

        $codePull = Artisan::call('sync:pull');
        $sortiePull = trim(Artisan::output());

        $codePush = Artisan::call('sync:push');
        $sortiePush = trim(Artisan::output());

        return ApiResponse::success([
            'pull' => ['code' => $codePull, 'message' => $sortiePull],
            'push' => ['code' => $codePush, 'message' => $sortiePush],
        ]);
    }
}
