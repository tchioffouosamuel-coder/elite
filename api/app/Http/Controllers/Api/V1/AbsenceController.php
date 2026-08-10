<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveAbsencesRequest;
use App\Models\Classe;
use App\Models\Trimestre;
use App\Services\DisciplineService;
use App\Support\Pdf\MpdfFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbsenceController extends Controller
{
    public function __construct(private readonly DisciplineService $service) {}

    public function index(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->findOrFail($request->integer('trimestre_id'));

        return ApiResponse::success($this->service->grille($classe, $trimestre));
    }

    public function bulkStore(BulkSaveAbsencesRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->findOrFail($request->integer('trimestre_id'));

        $count = $this->service->sauvegarderEnLot($classe, $trimestre, $request->input('absences'), $request->user());

        return ApiResponse::success(['saved' => $count], "{$count} enregistrement(s) mis à jour.");
    }

    public function bilan(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->findOrFail($request->integer('trimestre_id'));

        return ApiResponse::success($this->service->bilanClasse($classe, $trimestre));
    }

    public function bilanPdf(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->with('school')->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->with('anneeScolaire')->findOrFail($request->integer('trimestre_id'));

        return MpdfFactory::streamFromView('pdf.bilan-disciplinaire', [
            'classe' => $classe,
            'trimestre' => $trimestre,
            'bilan' => $this->service->bilanClasse($classe, $trimestre),
            'eleves' => $this->service->lignesDetail($classe, $trimestre),
        ], "bilan-disciplinaire-{$classe->nom}.pdf");
    }
}
