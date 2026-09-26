<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class MigrationStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $exitCode = Artisan::call('migrate:status', ['--no-ansi' => true]);

        return ApiResponse::success([
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ], 'État des migrations récupéré.');
    }
}
