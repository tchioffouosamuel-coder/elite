<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSanctionRequest;
use App\Http\Resources\Api\V1\SanctionResource;
use App\Models\Eleve;
use App\Models\Sanction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sanctions = Sanction::forSchool(app('tenant.school_id'))
            ->with(['eleve', 'classe', 'enregistrePar'])
            ->when($request->integer('eleve_id'), fn ($q, $id) => $q->where('eleve_id', $id))
            ->when($request->integer('classe_id'), fn ($q, $id) => $q->where('classe_id', $id))
            ->when($request->integer('trimestre_id'), fn ($q, $id) => $q->where('trimestre_id', $id))
            ->latest('date_sanction')
            ->get();

        return ApiResponse::success(SanctionResource::collection($sanctions));
    }

    public function store(StoreSanctionRequest $request): JsonResponse
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->findOrFail($request->integer('eleve_id'));

        if (! $eleve->classe_id) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $sanction = Sanction::create([
            ...$request->validated(),
            'classe_id' => $eleve->classe_id,
            'enregistre_par' => $request->user()->personnel?->id,
        ])->load(['eleve', 'classe', 'enregistrePar']);

        return ApiResponse::created(new SanctionResource($sanction), 'Sanction enregistrée.');
    }

    public function destroy(int $id): JsonResponse
    {
        $sanction = Sanction::forSchool(app('tenant.school_id'))->findOrFail($id);
        $sanction->delete();

        return ApiResponse::success(message: 'Sanction supprimée.');
    }
}
