<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionTrimestre;
use App\Http\Controllers\Controller;
use App\Services\RapportTrimestreService;
use App\Support\Word\RapportTrimestreWordGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Assemblage complet du rapport de fin de trimestre MINEDUB — vue d'ensemble JSON et export .docx. */
class RapportTrimestreExportController extends Controller
{
    use ResolutionTrimestre;

    public function __construct(private readonly RapportTrimestreService $service) {}

    public function donnees(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = $this->resolveTrimestre($request, $schoolId);

        return ApiResponse::success($this->service->generer($schoolId, $trimestre));
    }

    public function docx(Request $request): BinaryFileResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = $this->resolveTrimestre($request, $schoolId);

        $donnees = $this->service->generer($schoolId, $trimestre);
        $path = (new RapportTrimestreWordGenerator)->build($donnees);

        return response()
            ->download($path, 'rapport-fin-trimestre.docx')
            ->deleteFileAfterSend();
    }
}
