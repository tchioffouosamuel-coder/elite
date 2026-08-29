<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangerMotDePasseRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UpdateProfilRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('identifiant')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'web')->toString(),
        );

        if (! $result) {
            return ApiResponse::error('Identifiants incorrects ou compte désactivé.', 401);
        }

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token'],
        ], 'Connexion réussie.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refresh(
            $request->user(),
            $request->string('device_name', 'web')->toString(),
        );

        if (! $result) {
            return ApiResponse::error("Ce jeton ne permet pas de renouveler la session.", 401);
        }

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token'],
        ], 'Jeton renouvelé.');
    }

    public function updateProfil(UpdateProfilRequest $request): JsonResponse
    {
        $user = $this->authService->mettreAJourProfil($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user), 'Profil mis à jour.');
    }

    public function changerMotDePasse(ChangerMotDePasseRequest $request): JsonResponse
    {
        $change = $this->authService->changerMotDePasse(
            $request->user(),
            $request->string('ancien_mot_de_passe')->toString(),
            $request->string('nouveau_mot_de_passe')->toString(),
        );

        if (! $change) {
            return ApiResponse::validationError(
                ['ancien_mot_de_passe' => ['Le mot de passe actuel est incorrect.']],
            );
        }

        return ApiResponse::success(
            new UserResource($request->user()->fresh()),
            'Mot de passe mis à jour.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(message: 'Déconnexion réussie.');
    }
}
