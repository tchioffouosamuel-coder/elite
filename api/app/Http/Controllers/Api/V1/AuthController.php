<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
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
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'web')->toString(),
        );

        if (! $result) {
            return ApiResponse::error('Identifiants incorrects ou compte désactivé.', 401);
        }

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
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

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Jeton renouvelé.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(message: 'Déconnexion réussie.');
    }
}
