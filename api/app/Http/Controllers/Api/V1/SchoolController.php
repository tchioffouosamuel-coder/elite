<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSchoolRequest;
use App\Http\Resources\Api\V1\SchoolResource;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    public function show(): JsonResponse
    {
        $school = School::with('niveaux')->findOrFail(app('tenant.school_id'));

        return ApiResponse::success(new SchoolResource($school));
    }

    public function update(UpdateSchoolRequest $request): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));
        $data = $request->validated();

        DB::transaction(function () use ($school, $data) {
            $school->update([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'header_fr' => $data['header_fr'] ?? null,
                'header_en' => $data['header_en'] ?? null,
            ]);
            $school->niveaux()->sync($data['niveau_ids']);
        });

        return ApiResponse::success(new SchoolResource($school->refresh()->load('niveaux')), 'Profil de l\'établissement mis à jour.');
    }
}
