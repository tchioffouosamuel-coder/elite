<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Salle;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalleController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Salle::forSchool(Tenant::schoolIds())->orderBy('nom')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $schoolId = (int) Tenant::schoolIds()[0];
        $salle = Salle::create([...$data, 'school_id' => $schoolId]);

        return ApiResponse::created($salle, 'Salle créée.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $salle = Salle::forSchool(Tenant::schoolIds())->findOrFail($id);
        $salle->update($request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return ApiResponse::success($salle->fresh(), 'Salle mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $salle = Salle::forSchool(Tenant::schoolIds())->findOrFail($id);
        $salle->update(['active' => false]);

        return ApiResponse::success(message: 'Salle désactivée.');
    }
}
