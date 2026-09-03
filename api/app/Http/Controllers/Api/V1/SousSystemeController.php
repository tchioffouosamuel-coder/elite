<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SousSystemeResource;
use App\Models\SousSysteme;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\SousSystemeSpec;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SousSystemeController extends Controller
{
    use GereImportExport;

    protected function specificationImportExport(): SpecificationModele
    {
        return new SousSystemeSpec();
    }

    public function index(Request $request): JsonResponse
    {
        $sousSystemes = SousSysteme::forSchool(Tenant::schoolIds())->with('school:id,name,code,type')->get();

        return ApiResponse::success(SousSystemeResource::collection($sousSystemes));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:sous_systemes,code'],
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $data['school_id'] = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        $sousSysteme = SousSysteme::create($data);
        $sousSysteme->load('school:id,name,code,type');

        return ApiResponse::created(new SousSystemeResource($sousSysteme), 'Sous-système créé.');
    }

    public function show(int $id): JsonResponse
    {
        $sousSysteme = SousSysteme::forSchool(Tenant::schoolIds())->with('school:id,name,code,type')->findOrFail($id);

        return ApiResponse::success(new SousSystemeResource($sousSysteme));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $sousSysteme = SousSysteme::forSchool(Tenant::schoolIds())->findOrFail($id);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:10', 'unique:sous_systemes,code,' . $id],
            'nom' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $sousSysteme->update($data);
        $sousSysteme->load('school:id,name,code,type');

        return ApiResponse::success(new SousSystemeResource($sousSysteme), 'Sous-système mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $sousSysteme = SousSysteme::forSchool(Tenant::schoolIds())->findOrFail($id);

        if ($sousSysteme->classes()->count() > 0) {
            return ApiResponse::error('Ce sous-système est utilisé par des classes. Impossible de le supprimer.', 422);
        }

        $sousSysteme->delete();

        return ApiResponse::success(null, 'Sous-système supprimé.');
    }
}
