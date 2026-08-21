<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\ScolariteService;
use App\Support\Pdf\InsolvablesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsolvablesController extends Controller
{
    public function __construct(private readonly ScolariteService $service) {}

    public function index(Request $request): JsonResponse
    {
        $situation = $this->service->insolvables($this->schoolIds($request), [
            'classe_id' => $request->integer('classe_id') ?: null,
        ]);

        return ApiResponse::success($situation);
    }

    public function pdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $situation = $this->service->insolvables($schoolIds, [
            'classe_id' => $request->integer('classe_id') ?: null,
        ]);

        // Un document imprimé porte l'en-tête d'un seul établissement : en
        // mode agrégé, celui de la première école du périmètre plutôt que rien.
        $school = School::whereIn('id', $schoolIds)->first();

        $pdf = (new InsolvablesGenerator)->build($school, $situation['lignes'], $situation['totaux']);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-insolvables.pdf"',
        ]);
    }

    /** @return list<int> */
    private function schoolIds(Request $request): array
    {
        $requested = $request->integer('school_id');
        if (! $requested) {
            return Tenant::schoolIds();
        }

        abort_unless(in_array($requested, Tenant::schoolIds(), true), 403, "Cet établissement n'est pas accessible à votre compte.");

        return [$requested];
    }
}
