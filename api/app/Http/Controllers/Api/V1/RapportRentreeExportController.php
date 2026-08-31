<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionAnneeScolaire;
use App\Http\Controllers\Controller;
use App\Services\RapportRentreeService;
use App\Support\Pdf\RapportRentreeGenerator;
use App\Support\Word\RapportRentreeWordGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Assemblage complet du rapport de rentrée MINEDUB — vue d'ensemble JSON et export PDF. */
class RapportRentreeExportController extends Controller
{
    use ResolutionAnneeScolaire;

    public function __construct(private readonly RapportRentreeService $service) {}

    public function donnees(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->generer($schoolId, $annee));
    }

    public function pdf(Request $request): Response
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        $donnees = $this->service->generer($schoolId, $annee);
        $pdf = (new RapportRentreeGenerator)->build($donnees);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapport-rentree-scolaire.pdf"',
        ]);
    }

    /**
     * Version .docx du même rapport, fidèle au canevas MINEDUB (numérotation
     * "Tableau N°X", en-tête et pied de page administratifs) — éditable dans
     * Word pour correction manuelle avant dépôt à la délégation.
     */
    public function docx(Request $request): BinaryFileResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        $donnees = $this->service->generer($schoolId, $annee);
        $path = (new RapportRentreeWordGenerator)->build($donnees);

        return response()
            ->download($path, 'rapport-rentree-scolaire.docx')
            ->deleteFileAfterSend();
    }
}
