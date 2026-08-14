<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMatiereRequest;
use App\Http\Resources\Api\V1\MatiereResource;
use App\Imports\MatiereImport;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Matiere::forSchool(app('tenant.school_id'))->with('departement')->orderBy('nom')->get();

        return ApiResponse::success(MatiereResource::collection($matieres));
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $matiere = Matiere::create([...$request->validated(), 'school_id' => app('tenant.school_id')])->refresh();

        return ApiResponse::created(new MatiereResource($matiere), 'Matière créée.');
    }

    public function update(StoreMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $matiere->update($request->validated());

        return ApiResponse::success(new MatiereResource($matiere), 'Matière mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $matiere->delete();

        return ApiResponse::success(message: 'Matière supprimée.');
    }

    public function batchDestroy(): JsonResponse
    {
        $ids = request()->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucune matière à supprimer.');
        }

        Matiere::forSchool(app('tenant.school_id'))->whereIn('id', $ids)->delete();

        return ApiResponse::success(message: count($ids) . ' matière(s) supprimée(s).');
    }

    /** Primaire et maternelle uniquement — le secondaire rattache ses matières à un département. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new MatiereImport(app('tenant.school_id'));
        Excel::import($import, $request->file('file'));

        $result = [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];

        return ApiResponse::success($result, "{$result['imported']} matière(s) importée(s).");
    }
}
