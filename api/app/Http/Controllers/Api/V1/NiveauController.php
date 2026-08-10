<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;

class NiveauController extends Controller
{
    public function index(): JsonResponse
    {
        $niveaux = Niveau::orderBy('ordre')->get(['id', 'code', 'name_fr', 'name_en']);

        return ApiResponse::success($niveaux);
    }
}
