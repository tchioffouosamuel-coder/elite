<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMatiereRequest;
use App\Http\Resources\Api\V1\MatiereResource;
use App\Imports\MatiereImport;
use App\Models\Matiere;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Matiere::forSchool(Tenant::schoolIds())
            ->with(['departement', 'school:id,name,code,type'])
            ->withCount('classeMatieres')
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(MatiereResource::collection($matieres));
    }

    /** Classes où cette matière est enseignée — pour la modale « Enseignée dans X classe(s) ». */
    public function classes(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);

        $affectations = $matiere->classeMatieres()
            ->with(['classe', 'enseignant'])
            ->get()
            ->sortBy(fn ($a) => $a->classe?->nom)
            ->values();

        return ApiResponse::success($affectations->map(fn ($a) => [
            'classe_matiere_id' => $a->id,
            'classe' => $a->classe ? ['id' => $a->classe->id, 'nom' => $a->classe->nom] : null,
            'enseignant' => $a->enseignant ? ['id' => $a->enseignant->id, 'nom_complet' => $a->enseignant->nom_complet] : null,
            'coefficient' => $a->coefficient,
        ])->values());
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $matiere = Matiere::create([...$data, 'school_id' => $schoolId])->refresh();
        $matiere->load('school:id,name,code,type');

        return ApiResponse::created(new MatiereResource($matiere), 'Matière créée.');
    }

    public function update(StoreMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);
        $matiere->update($data);
        $matiere->load('school:id,name,code,type');

        return ApiResponse::success(new MatiereResource($matiere), 'Matière mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);
        $matiere->delete();

        return ApiResponse::success(message: 'Matière supprimée.');
    }

    public function batchDestroy(): JsonResponse
    {
        $ids = request()->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucune matière à supprimer.');
        }

        Matiere::forSchool(Tenant::schoolIds())->whereIn('id', $ids)->delete();

        return ApiResponse::success(message: count($ids) . ' matière(s) supprimée(s).');
    }

    /** Primaire et maternelle uniquement — le secondaire rattache ses matières à un département. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new MatiereImport(Tenant::schoolId());
        Excel::import($import, $request->file('file'));

        $result = [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];

        return ApiResponse::success($result, "{$result['imported']} matière(s) importée(s).");
    }
}
