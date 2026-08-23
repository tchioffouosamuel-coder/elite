<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Support\Facades\Hash;

class AuthService extends BaseService
{
    /**
     * Le personnel se connecte par e-mail, les parents par téléphone (cf.
     * CompteParentService, qui n'ouvre pas d'adresse) : `$identifiant` peut
     * être l'un ou l'autre, distingués par la présence d'un « @ ». Un numéro
     * doit passer par la même normalisation qu'à l'ouverture du compte, sans
     * quoi la moindre variante de saisie (espaces, préfixe 0…) le rendrait
     * introuvable.
     *
     * @return array{user: User, token: string}|null null when the credentials are invalid or the account is disabled.
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

        $token = $user->createToken($deviceName)->plainTextToken;

        ActivityLog::enregistrer($user, 'connexion', 'Connexion à l’application.');

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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

        $user->forceFill([
            'password' => Hash::make($nouveau),
            'doit_changer_mot_de_passe' => false,
        ])->save();

        $user->tokens()->whereKeyNot($courant?->getKey())->delete();

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
     * @return array{user: User, token: string}
     */
    public function refresh(User $user, string $deviceName = 'web'): array
    {
        $user->currentAccessToken()->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
