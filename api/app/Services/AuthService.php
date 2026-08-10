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
     * @return array{user: User, token: string}
     */
    public function refresh(User $user, string $deviceName = 'web'): array
    {
        $user->currentAccessToken()->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
