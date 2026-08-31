<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DesktopProvisioning;
use App\Models\School;
use App\Models\SyncOutbox;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
     * n'appelle CE endpoint qu'une seule fois, au tout premier lancement).
     *
     * Mono-utilisateur : une instance déjà provisionnée refuse un second
     * provisioning tant qu'elle n'a pas été explicitement réinitialisée —
     * il n'y a pas de changement de compte sur un poste desktop.
     */
    public function provisionner(Request $request): JsonResponse
    {
        if (DesktopProvisioning::actuelle() !== null) {
            return ApiResponse::error('Ce poste est déjà lié à un compte.', 409);
        }

        $data = $request->validate([
            'serveur_url' => ['required', 'url'],
            'token' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
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
            'school' => ['nullable', 'array'],
            'school.name' => ['required_with:school', 'string', 'max:255'],
            'school.code' => ['required_with:school', 'string', 'max:50'],
            'school.type' => ['required_with:school', 'string', 'max:50'],
        ]);

        $schoolId = null;

        // `id` n'est fillable ni sur `School` ni sur `User` : `updateOrCreate`
        // laisserait silencieusement l'auto-incrément attribuer un autre
        // identifiant que celui du serveur distant, brisant toute
        // correspondance avec les lignes reçues ensuite par `sync:pull`.
        // L'affectation directe (`->id =`) contourne le mass-assignment pour
        // cette seule colonne.
        if (isset($data['school']) && isset($data['user']['school_id'])) {
            $ecole = School::find($data['user']['school_id']) ?? new School();
            $ecole->id = $data['user']['school_id'];
            $ecole->fill(['name' => $data['school']['name'], 'code' => $data['school']['code'], 'type' => $data['school']['type'], 'is_active' => true]);
            $ecole->save();
            $schoolId = $data['user']['school_id'];
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
        // Jamais vérifié : `session()` authentifie ce compte sans mot de
        // passe, seul un utilisateur ayant l'accès physique au poste pouvant
        // l'atteindre.
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
            'school_id' => $schoolId,
            'serveur_url' => $data['serveur_url'],
            'token' => $data['token'],
            'refresh_token' => $data['refresh_token'],
            'provisionne_le' => now(),
        ]);

        Artisan::call('sync:pull');

        return ApiResponse::created([
            'provisionne' => true,
            'utilisateur' => ['id' => $utilisateur->id, 'name' => $utilisateur->name],
            'pull' => trim(Artisan::output()),
        ], 'Poste lié avec succès.');
    }

    /**
     * Authentifie automatiquement l'unique compte lié à ce poste, sans mot
     * de passe : un seul utilisateur du système d'exploitation a accès à
     * cette machine, et il n'existe qu'un seul compte applicatif dessus.
     */
    public function session(): JsonResponse
    {
        $provisioning = DesktopProvisioning::actuelle();

        if ($provisioning === null) {
            return ApiResponse::notFound('Ce poste n’est lié à aucun compte.');
        }

        $utilisateur = $provisioning->user;
        $jeton = $utilisateur->createToken('desktop-session', ['access']);

        return ApiResponse::success([
            'user' => $utilisateur,
            'token' => $jeton->plainTextToken,
        ]);
    }

    public function statutSync(): JsonResponse
    {
        $provisioning = DesktopProvisioning::actuelle();

        if ($provisioning === null) {
            return ApiResponse::notFound('Ce poste n’est lié à aucun compte.');
        }

        return ApiResponse::success([
            'dernier_pull_le' => $provisioning->dernier_pull_le?->toIso8601String(),
            'dernier_push_le' => $provisioning->dernier_push_le?->toIso8601String(),
            'en_attente_push' => SyncOutbox::query()->enAttente()->count(),
        ]);
    }

    /** Déclenche un cycle pull puis push, pour un bouton « Synchroniser maintenant ». */
    public function synchroniser(): JsonResponse
    {
        if (DesktopProvisioning::actuelle() === null) {
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
