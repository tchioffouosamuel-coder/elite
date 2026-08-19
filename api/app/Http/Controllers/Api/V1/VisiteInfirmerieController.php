<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVisiteInfirmerieRequest;
use App\Http\Requests\Api\V1\UpdateVisiteInfirmerieRequest;
use App\Http\Resources\Api\V1\VisiteInfirmerieResource;
use App\Models\Eleve;
use App\Models\VisiteInfirmerie;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisiteInfirmerieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'eleve_id' => ['nullable', 'integer'],
            'classe_id' => ['nullable', 'integer'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $visites = VisiteInfirmerie::forSchool(Tenant::schoolIds())
            ->with(['eleve.school', 'classe', 'enregistrePar'])
            ->when($request->integer('eleve_id'), fn ($q, $id) => $q->where('eleve_id', $id))
            ->when($request->integer('classe_id'), fn ($q, $id) => $q->where('classe_id', $id))
            ->when($request->string('du')->toString(), fn ($q, $du) => $q->whereDate('date_visite', '>=', $du))
            ->when($request->string('au')->toString(), fn ($q, $au) => $q->whereDate('date_visite', '<=', $au))
            ->latest('date_visite')
            ->get();

        return ApiResponse::success(VisiteInfirmerieResource::collection($visites));
    }

    public function store(StoreVisiteInfirmerieRequest $request): JsonResponse
    {
        $eleve = $this->eleve($request->integer('eleve_id'));

        $visite = VisiteInfirmerie::create([
            ...$request->validated(),
            'classe_id' => $eleve->classe_id,
            'cout_soins' => $request->integer('cout_soins'),
            'enregistre_par' => $request->user()->personnel?->id,
        ])->load(['eleve.school', 'classe', 'enregistrePar']);

        return ApiResponse::created(new VisiteInfirmerieResource($visite), 'Visite à l’infirmerie enregistrée.');
    }

    public function update(UpdateVisiteInfirmerieRequest $request, int $id): JsonResponse
    {
        $visite = $this->visite($id);
        $eleve = $this->eleve($request->integer('eleve_id'));

        $visite->update([
            ...$request->validated(),
            'classe_id' => $eleve->classe_id,
            'cout_soins' => $request->integer('cout_soins'),
        ]);

        return ApiResponse::success(
            new VisiteInfirmerieResource($visite->fresh(['eleve.school', 'classe', 'enregistrePar'])),
            'Visite à l’infirmerie mise à jour.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->visite($id)->delete();

        return ApiResponse::success(message: 'Visite à l’infirmerie supprimée.');
    }

    private function visite(int $id): VisiteInfirmerie
    {
        return VisiteInfirmerie::forSchool(Tenant::schoolIds())->findOrFail($id);
    }

    private function eleve(int $id): Eleve
    {
        return Eleve::forSchool(Tenant::schoolIds())->findOrFail($id);
    }
}
