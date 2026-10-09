<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HistoriqueActionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoriqueActionsController extends Controller
{
    public function __construct(private readonly HistoriqueActionsService $service) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->etat($request->user()));
    }

    public function annuler(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success($this->service->executer($request, $uuid, false), 'Action annulee.');
    }

    public function retablir(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success($this->service->executer($request, $uuid, true), 'Action retablie.');
    }
}
