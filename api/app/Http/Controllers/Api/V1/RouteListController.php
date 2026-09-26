<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

class RouteListController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(fn(IlluminateRoute $route) => [
                'domain' => $route->domain(),
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
            ])
            ->values();

        return ApiResponse::success([
            'count' => count($routes),
            'routes' => $routes,
        ], 'Liste des routes récupérée.');
    }
}
