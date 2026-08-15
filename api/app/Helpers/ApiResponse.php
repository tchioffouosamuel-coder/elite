<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = ''): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null, string $message = ''): JsonResponse
    {
        $items = $resourceClass ? $resourceClass::collection($paginator->items()) : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $items,
            'message' => $message,
            'errors' => null,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
            'meta' => null,
        ], $status);
    }

    public static function validationError(mixed $errors, string $message = 'Les données envoyées ne sont pas valides.'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    public static function notFound(string $message = 'Ressource introuvable.'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * @param  list<string>|null  $permissionsRequises  privilèges qui auraient permis l'action,
     *                                                  pour que l'interface puisse les nommer.
     */
    public static function forbidden(string $message = "Vous n'avez pas la permission d'effectuer cette action.", ?array $permissionsRequises = null): JsonResponse
    {
        return self::error($message, 403, $permissionsRequises ? ['permissions_requises' => $permissionsRequises] : null);
    }

    public static function unauthorized(string $message = 'Authentification requise.'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function serverError(string $message = 'Une erreur interne est survenue.'): JsonResponse
    {
        return self::error($message, 500);
    }
}
