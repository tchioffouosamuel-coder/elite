<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Support\Facades\Hash;

class AuthService extends BaseService
{
    /**
     * Durée de vie du jeton d'accès : volontairement courte, puisque
     * {@see refresh()} permet de le renouveler sans ressaisie tant que le
     * jeton de rafraîchissement (lui-même valable {@see REFRESH_TOKEN_TTL_MINUTES})
     * n'a pas expiré.
     */
    private const ACCESS_TOKEN_TTL_MINUTES = 60 * 24;

    /** Aligné sur l'ancienne durée de session unique, pour ne pas raccourcir l'expérience actuelle. */
    private const REFRESH_TOKEN_TTL_MINUTES = 60 * 24 * 30;

    /**
     * Le personnel se connecte par e-mail, les parents par téléphone (cf.
     * CompteParentService, qui n'ouvre pas d'adresse) : `$identifiant` peut
     * être l'un ou l'autre, distingués par la présence d'un « @ ». Un numéro
     * doit passer par la même normalisation qu'à l'ouverture du compte, sans
     * quoi la moindre variante de saisie (espaces, préfixe 0…) le rendrait
     * introuvable.
     *
     * @return array{user: User, token: string, refresh_token: string}|null null when the credentials are invalid or the account is disabled.
     */
    public function login(string $identifiant, string $password, string $deviceName = 'web'): ?array
    {
        $identifiant = trim($identifiant);

        $user = str_contains($identifiant, '@')
            ? User::where('email', $identifiant)->first()
            : User::where('phone', Telephone::normaliser($identifiant))->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            return null;
        }

        ActivityLog::enregistrer($user, 'connexion', 'Connexion à l’application.');

        return ['user' => $user, ...$this->emettreJetons($user, $deviceName)];
    }

    /**
     * Révoque le jeton d'accès courant ainsi que son jeton de rafraîchissement
     * associé (même paire de session) : sans quoi une déconnexion laisserait
     * le jeton de rafraîchissement valide, capable de rouvrir la session.
     */
    public function logout(User $user): void
    {
        $current = $user->currentAccessToken();

        $user->tokens()->whereIn('name', $this->nomsPaire($current?->name))->delete();
    }

    /**
     * Révoque toutes les sessions ouvertes d'un compte — à appeler partout où
     * un accès est bloqué (personnel archivé, accès parent débloqué→bloqué).
     * Sans ça, un compte désactivé resterait utilisable jusqu'à l'expiration
     * naturelle de son jeton d'accès (24 h) et surtout de son jeton de
     * rafraîchissement (30 jours) : un mode hors-ligne côté mobile qui se
     * fierait à un 401 pour détecter la désactivation ne le verrait jamais.
     */
    public function revoquerTousLesJetons(User $user): void
    {
        $user->tokens()->delete();
    }

    /** Identité du compte (nom, e-mail, téléphone) — distincte de la fiche personnel, gérée séparément par un administrateur. */
    public function mettreAJourProfil(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    /**
     * Renouvellement du mot de passe par l'intéressé.
     *
     * L'ancien mot de passe est exigé même lors du changement obligatoire :
     * l'agent vient de le saisir pour entrer, et une session laissée ouverte
     * sur un poste partagé ne doit pas suffire à s'approprier le compte.
     *
     * Les autres jetons sont révoqués : si le mot de passe commun avait servi
     * à quelqu'un d'autre, sa session tombe.
     */
    public function changerMotDePasse(User $user, string $ancien, string $nouveau): bool
    {
        if (! Hash::check($ancien, $user->password)) {
            return false;
        }

        $courant = $user->currentAccessToken();
        // Le jeton de rafraîchissement de la même session doit survivre : sinon
        // la prochaine tentative de renouvellement échouerait juste après un
        // changement de mot de passe pourtant réussi.
        $paire = $this->nomsPaire($courant?->name);

        $user->forceFill([
            'password' => Hash::make($nouveau),
            'doit_changer_mot_de_passe' => false,
        ])->save();

        $user->tokens()->whereNotIn('name', $paire)->delete();

        return true;
    }

    /**
     * Réinitialisation forcée par le super administrateur.
     *
     * À la différence de {@see changerMotDePasse()}, aucun mot de passe actuel
     * n'est exigé — c'est précisément l'intérêt de la fonction pour un compte
     * bloqué ou dont le titulaire ne peut plus se connecter. Le compte devra
     * le changer dès sa prochaine connexion, et toutes ses sessions ouvertes
     * tombent : un accès ainsi réinitialisé ne doit profiter qu'à qui vient de
     * le recevoir.
     */
    public function reinitialiserMotDePasse(User $cible, string $nouveau): void
    {
        $cible->forceFill([
            'password' => Hash::make($nouveau),
            'doit_changer_mot_de_passe' => true,
        ])->save();

        $cible->tokens()->delete();
    }

    /**
     * Renouvellement de session à partir du jeton de rafraîchissement.
     *
     * Refusé si le jeton présenté est le jeton d'accès (ou tout jeton sans
     * l'aptitude `refresh`) : un jeton d'accès qui fuit ne doit pas suffire à
     * prolonger indéfiniment la session, seul le jeton de rafraîchissement
     * — distinct, jamais envoyé aux endpoints métier — le peut.
     *
     * L'ancien jeton de rafraîchissement est révoqué (rotation) : le
     * réutiliser après ce point échouera, ce qui permet de détecter un vol.
     *
     * @return array{user: User, token: string, refresh_token: string}|null null when the presented token cannot refresh a session.
     */
    public function refresh(User $user, string $deviceName = 'web'): ?array
    {
        $current = $user->currentAccessToken();

        if (! $current instanceof \Laravel\Sanctum\PersonalAccessToken || ! $current->can('refresh')) {
            return null;
        }

        $current->delete();

        return ['user' => $user, ...$this->emettreJetons($user, $deviceName)];
    }

    /**
     * @return array{token: string, refresh_token: string}
     *
     * L'identifiant aléatoire distingue deux sessions qui partageraient le
     * même `$deviceName` (le mobile n'en envoie aucun et retombe toujours sur
     * le défaut `web`, tout comme le client web) : sans lui, {@see logout()}
     * et {@see changerMotDePasse()} ne pourraient pas savoir quel jeton de
     * rafraîchissement appartient à quelle paire.
     */
    private function emettreJetons(User $user, string $deviceName): array
    {
        $sessionId = bin2hex(random_bytes(4));

        $access = $user->createToken(
            "{$deviceName}-{$sessionId}-access",
            ['access'],
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
        );

        $refresh = $user->createToken(
            "{$deviceName}-{$sessionId}-refresh",
            ['refresh'],
            now()->addMinutes(self::REFRESH_TOKEN_TTL_MINUTES),
        );

        return ['token' => $access->plainTextToken, 'refresh_token' => $refresh->plainTextToken];
    }

    /** Les deux noms de jeton (accès + rafraîchissement) d'une même session, à partir du nom de l'un d'eux. */
    private function nomsPaire(?string $nomJeton): array
    {
        $base = preg_replace('/-(access|refresh)$/', '', $nomJeton ?? '');

        return ["{$base}-access", "{$base}-refresh"];
    }
}
