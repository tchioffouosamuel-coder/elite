<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNiveauRequest;
use App\Http\Requests\Api\V1\UpdateNiveauRequest;
use App\Http\Resources\Api\V1\NiveauResource;
use App\Models\Niveau;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\NiveauSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NiveauController extends Controller
{
    use GereImportExport;

    protected function specificationImportExport(): SpecificationModele
    {
        return new NiveauSpec();
    }

    public function index(Request $request): ResourceCollection
    {
        $schoolId = $request->query('school_id');

        $query = Niveau::orderBy('id');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $niveaux = $query->paginate(50);

        return NiveauResource::collection($niveaux);
    }

    public function store(StoreNiveauRequest $request): JsonResponse
    {
        $niveau = Niveau::create($request->validated());

        return ApiResponse::created(
            new NiveauResource($niveau),
            'Niveau créé avec succès'
        );
    }

    public function show(int $id): JsonResponse
    {
        $niveau = Niveau::findOrFail($id);

        return ApiResponse::success(new NiveauResource($niveau));
    }

    public function update(UpdateNiveauRequest $request, int $id): JsonResponse
    {
        $niveau = Niveau::findOrFail($id);
        $niveau->update($request->validated());

        return ApiResponse::success(
            new NiveauResource($niveau),
            'Niveau mis à jour avec succès'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $niveau = Niveau::findOrFail($id);
        $niveau->delete();

        return ApiResponse::success(
            data: null,
            message: 'Niveau supprimé avec succès'
        );
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucun ID fourni', 400);
        }

        $count = Niveau::whereIn('id', $ids)->delete();

        return ApiResponse::success(
            data: ['count' => $count],
            message: sprintf('%d niveau(x) supprimé(s) avec succès', $count)
        );
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $sousSystemId = $request->input('sous_system_id');
        $schoolId = $request->input('school_id');

        if (empty($ids)) {
            return ApiResponse::error('Aucun ID fourni', 400);
        }

        $payload = [];

        if ($request->has('sous_system_id')) {
            if ($sousSystemId !== null && $sousSystemId !== '') {
                $request->validate([
                    'sous_system_id' => 'nullable|integer|exists:sous_systemes,id',
                ]);
                $payload['sous_system_id'] = (int) $sousSystemId;
            } else {
                $payload['sous_system_id'] = null;
            }
        }

        if ($request->has('school_id')) {
            if ($schoolId !== null && $schoolId !== '') {
                $request->validate([
                    'school_id' => 'nullable|integer|exists:schools,id',
                ]);
                $payload['school_id'] = (int) $schoolId;
            } else {
                $payload['school_id'] = null;
            }
        }

        if (empty($payload)) {
            return ApiResponse::error('Aucune donnée de mise à jour fournie', 400);
        }

        $count = Niveau::whereIn('id', $ids)->update($payload);

        return ApiResponse::success(
            data: ['count' => $count],
            message: sprintf('%d niveau(x) mis à jour avec succès', $count)
        );
    }
}
