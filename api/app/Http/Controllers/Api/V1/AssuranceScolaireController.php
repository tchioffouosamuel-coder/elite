<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAssuranceScolaireRequest;
use App\Http\Requests\Api\V1\UpdateAssuranceScolaireRequest;
use App\Models\AnneeScolaire;
use App\Models\AssuranceScolaire;
use App\Services\AssuranceScolaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Assurance scolaire par groupe de niveaux — tableau 26 du rapport de rentrée MINEDUB. */
class AssuranceScolaireController extends Controller
{
    public function __construct(private readonly AssuranceScolaireService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $request->integer('annee_scolaire_id')
            ? AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'))->id
            : AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail()->id;

        return ApiResponse::success($this->service->list($schoolId, $annee));
    }

    public function store(StoreAssuranceScolaireRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->create($schoolId, $data), 'Assurance enregistrée.');
    }

    public function update(UpdateAssuranceScolaireRequest $request, int $id): JsonResponse
    {
        $assurance = AssuranceScolaire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $assurance = $this->service->update($assurance, $request->validated());

        return ApiResponse::success($assurance, 'Assurance mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $assurance = AssuranceScolaire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->delete($assurance);

        return ApiResponse::success(null, 'Assurance supprimée.');
    }
}
