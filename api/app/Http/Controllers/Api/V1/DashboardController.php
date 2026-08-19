<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->stats(Tenant::schoolIds(), $request->user()));
    }
}
