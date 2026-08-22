<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ParentUsageStatsService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suivi d'adoption et d'usage du portail parent — adoption des comptes, activité, volumes déposés, délai de traitement. */
class ParentUsageStatsController extends Controller
{
    public function __construct(private readonly ParentUsageStatsService $service) {}

    public function index(Request $request): JsonResponse
    {
        $jours = $request->integer('jours') ?: 30;
        $jours = in_array($jours, [7, 30, 90], true) ? $jours : 30;

        return ApiResponse::success($this->service->resume(Tenant::schoolIds(), $jours));
    }
}
