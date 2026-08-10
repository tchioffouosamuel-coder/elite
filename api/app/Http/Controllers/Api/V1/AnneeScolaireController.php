<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAnneeScolaireRequest;
use App\Http\Resources\Api\V1\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnneeScolaireController extends Controller
{
    public function index(): JsonResponse
    {
        $annees = AnneeScolaire::where('school_id', app('tenant.school_id'))->orderByDesc('date_debut')->get();

        return ApiResponse::success(AnneeScolaireResource::collection($annees));
    }

    public function store(StoreAnneeScolaireRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $data = $request->validated();

        $annee = DB::transaction(function () use ($schoolId, $data) {
            if ($data['is_active'] ?? false) {
                AnneeScolaire::where('school_id', $schoolId)->update(['is_active' => false]);
            }

            return AnneeScolaire::create([...$data, 'school_id' => $schoolId]);
        });

        return ApiResponse::created(new AnneeScolaireResource($annee), 'Année scolaire créée.');
    }

    public function activate(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        DB::transaction(function () use ($schoolId, $annee) {
            AnneeScolaire::where('school_id', $schoolId)->update(['is_active' => false]);
            $annee->update(['is_active' => true]);
        });

        return ApiResponse::success(new AnneeScolaireResource($annee->refresh()), 'Année scolaire activée.');
    }
}
