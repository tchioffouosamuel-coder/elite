<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTrimestreRequest;
use App\Http\Resources\Api\V1\TrimestreResource;
use App\Models\AnneeScolaire;
use App\Models\Setting;
use App\Models\Trimestre;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TrimestreController extends Controller
{
    public function index(): JsonResponse
    {
        $trimestres = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->with('sequences')->orderBy('ordre')->get();

        return ApiResponse::success(TrimestreResource::collection($trimestres));
    }

    public function store(StoreTrimestreRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'));

        $trimestre = DB::transaction(function () use ($request, $schoolId) {
            $trimestre = Trimestre::create($request->validated());

            $numSequences = (int) Setting::get($schoolId, 'num_sequences', 2);
            for ($i = 1; $i <= $numSequences; $i++) {
                $trimestre->sequences()->create(['ordre' => $i, 'libelle' => "Séquence {$i}"]);
            }

            return $trimestre;
        });

        return ApiResponse::created(new TrimestreResource($trimestre->load('sequences')), 'Trimestre créé.');
    }

    public function activate(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId))->findOrFail($id);

        DB::transaction(function () use ($trimestre) {
            Trimestre::where('annee_scolaire_id', $trimestre->annee_scolaire_id)->update(['is_active' => false]);
            $trimestre->update(['is_active' => true]);
        });

        return ApiResponse::success(new TrimestreResource($trimestre->refresh()->load('sequences')), 'Trimestre activé.');
    }
}
