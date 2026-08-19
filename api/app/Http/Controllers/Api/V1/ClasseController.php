<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClasseRequest;
use App\Http\Resources\Api\V1\ClasseResource;
use App\Imports\ClasseImport;
use App\Services\ClasseService;
use App\Support\Attributions;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ClasseController extends Controller
{
    public function __construct(private readonly ClasseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $classes = $this->service->list(
            $request->user(),
            Tenant::schoolIds(),
            $request->integer('annee_scolaire_id') ?: null,
            $request->only(['niveau_id']),
        );

        return ApiResponse::success(ClasseResource::collection($classes));
    }

    public function store(StoreClasseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $classe = $this->service->create($schoolId, $data);

        return ApiResponse::created(new ClasseResource($classe), 'Classe créée.');
    }

    public function show(int $id): JsonResponse
    {
        $classe = $this->service->find(Tenant::schoolIds(), $id);

        return ApiResponse::success(new ClasseResource($classe));
    }

    /** Classe dont l'utilisateur connecté est titulaire (primaire/maternelle), ou null. */
    public function maClasse(Request $request): JsonResponse
    {
        $classe = $this->service->maClasse($request->user(), Tenant::schoolIds());

        return ApiResponse::success($classe ? new ClasseResource($classe) : null);
    }

    /**
     * Responsabilités nominatives du compte — professeur principal, surveillant
     * général, censeur, conseiller d'orientation, chef de département — avec
     * les classes concernées. Chaque attribution vient avec les privilèges
     * qu'elle confère, pour que l'interface sache ce qu'elle peut proposer
     * classe par classe sans les redéduire.
     */
    public function mesAttributions(Request $request): JsonResponse
    {
        $attributions = $this->service->mesAttributions($request->user(), Tenant::schoolIds())
            ->map(fn (array $attribution) => [
                ...$attribution,
                'permissions' => Attributions::permissions($attribution['code']),
                'classes' => ClasseResource::collection($attribution['classes']),
            ]);

        return ApiResponse::success($attributions);
    }

    public function update(StoreClasseRequest $request, int $id): JsonResponse
    {
        $classe = $this->service->find(Tenant::schoolIds(), $id);
        $data = $request->validated();
        unset($data['school_id']);
        $classe = $this->service->update($classe, $data);

        return ApiResponse::success(new ClasseResource($classe), 'Classe mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $classe = $this->service->find(Tenant::schoolIds(), $id);
        $this->service->delete($classe);

        return ApiResponse::success(message: 'Classe supprimée.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = app('tenant.school_id');
        $import = new ClasseImport($schoolId);
        Excel::import($import, $request->file('file'));

        $result = [
            'imported' => $import->importedCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
        ];

        return ApiResponse::success($result, "{$result['imported']} classe(s) importée(s).");
    }

    public function schools(): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $school = \App\Models\School::find($schoolId);

        if (!$school) {
            return ApiResponse::success([]);
        }

        $schools = \App\Models\School::where('complexe_id', $school->complexe_id)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($schools);
    }

    public function showSchool(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $school = \App\Models\School::find($schoolId);

        if (!$school) {
            return ApiResponse::error('Établissement non trouvé.', 404);
        }

        // Ensure the requested school is in the same complex
        $requestedSchool = \App\Models\School::where('complexe_id', $school->complexe_id)
            ->where('id', $id)
            ->first();

        if (!$requestedSchool) {
            return ApiResponse::error('Établissement non trouvé.', 404);
        }

        return ApiResponse::success($requestedSchool->only(['id', 'name', 'code']));
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_ids' => ['required', 'array'],
            'class_ids.*' => ['integer'],
            'school_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(Tenant::schoolIds())],
            'sous_systeme_id' => ['nullable', 'integer', 'exists:sous_systemes,id'],
            'niveau_id' => ['nullable', 'integer', 'exists:niveaux,id'],
        ]);

        $updates = array_filter([
            'school_id' => $validated['school_id'] ?? null,
            'sous_systeme_id' => $validated['sous_systeme_id'] ?? null,
            'niveau_id' => $validated['niveau_id'] ?? null,
        ], fn($v) => $v !== null);

        \App\Models\Classe::forSchool(Tenant::schoolIds())
            ->whereIn('id', $validated['class_ids'])
            ->update($updates);

        return ApiResponse::success(message: 'Classes mises à jour.');
    }
}
