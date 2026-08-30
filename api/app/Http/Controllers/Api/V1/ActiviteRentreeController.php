<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionAnneeScolaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreActiviteRentreeRequest;
use App\Http\Requests\Api\V1\UpdateActiviteRentreeRequest;
use App\Models\ActiviteRentree;
use App\Services\ActiviteRentreeService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Programmation et taux d'exécution — pédagogie/EPS/FENASSCO (tableaux 23-25 du rapport de rentrée MINEDUB). */
class ActiviteRentreeController extends Controller
{
    use ResolutionAnneeScolaire;

    public function __construct(private readonly ActiviteRentreeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['categorie' => ['nullable', 'in:pedagogique,eps,fenassco']]);

        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->list($schoolId, $annee, $request->string('categorie')->toString() ?: null));
    }

    public function store(StoreActiviteRentreeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        return ApiResponse::created($this->service->create($schoolId, $data), 'Activité enregistrée.');
    }

    public function update(UpdateActiviteRentreeRequest $request, int $id): JsonResponse
    {
        $activite = ActiviteRentree::forSchool(Tenant::schoolIds())->findOrFail($id);
        $activite = $this->service->update($activite, $request->validated());

        return ApiResponse::success($activite, 'Activité mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $activite = ActiviteRentree::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->delete($activite);

        return ApiResponse::success(null, 'Activité supprimée.');
    }
}
