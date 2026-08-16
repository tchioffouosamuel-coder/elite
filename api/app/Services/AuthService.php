<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService extends BaseService
{
    /**
     * @return array{user: User, token: string}|null null when the credentials are invalid or the account is disabled.
     */
    public function login(string $email, string $password, string $deviceName = 'web'): ?array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            return null;
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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
     * @return array{user: User, token: string}
     */
    public function refresh(User $user, string $deviceName = 'web'): array
    {
        $user->currentAccessToken()->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
