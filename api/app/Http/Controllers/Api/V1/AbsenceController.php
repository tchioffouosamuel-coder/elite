<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveAbsencesRequest;
use App\Models\Classe;
use App\Models\Trimestre;
use App\Services\DisciplineService;
use App\Services\EmploiDuTempsService;
use App\Support\Pdf\FicheAppelHebdomadaireGenerator;
use App\Support\Pdf\MpdfFactory;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class AbsenceController extends Controller
{
    public function __construct(
        private readonly DisciplineService $service,
        private readonly EmploiDuTempsService $emploiDuTemps,
    ) {}

    public function index(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('trimestre_id'));

        return ApiResponse::success($this->service->grille($classe, $trimestre));
    }

    public function bulkStore(BulkSaveAbsencesRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('trimestre_id'));

        $count = $this->service->sauvegarderEnLot($classe, $trimestre, $request->input('absences'), $request->user());

        return ApiResponse::success(['saved' => $count], "{$count} enregistrement(s) mis à jour.");
    }

    public function bilan(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('trimestre_id'));

        return ApiResponse::success($this->service->bilanClasse($classe, $trimestre));
    }

    /**
     * Taux de fréquentation trimestriel (par sexe) et taux de fréquentation
     * des minorités — rubriques du rapport de fin de trimestre MINEDUB.
     */
    public function frequentation(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('trimestre_id'));

        return ApiResponse::success([
            'par_sexe' => $this->service->tauxFrequentation($classe, $trimestre),
            'par_minorite' => $this->service->tauxFrequentationMinorites($classe, $trimestre),
        ]);
    }

    public function bilanPdf(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->with('anneeScolaire')->findOrFail($request->integer('trimestre_id'));

        return MpdfFactory::streamFromView('pdf.bilan-disciplinaire', [
            'classe' => $classe,
            'trimestre' => $trimestre,
            'bilan' => $this->service->bilanClasse($classe, $trimestre),
            'eleves' => $this->service->lignesDetail($classe, $trimestre),
        ], "bilan-disciplinaire-{$classe->nom}.pdf", school: $classe->school);
    }

    /**
     * Fiche d'appel hebdomadaire remplie (une colonne par période, croix
     * rouge sur les absences relevées à l'appel) — `semaine` est une date
     * quelconque de la semaine visée, ramenée à son lundi.
     */
    public function ficheHebdomadairePdf(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);

        $semaine = $request->date('semaine') ?? now();
        $lundi = Carbon::parse($semaine)->startOfWeek(Carbon::MONDAY);

        $grille = $this->emploiDuTemps->ficheAppelHebdomadaire($classe, $lundi);

        return response(
            (new FicheAppelHebdomadaireGenerator)->build($classe, $grille),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="fiche-appel-'.$classe->nom.'-'.$lundi->toDateString().'.pdf"',
            ]
        );
    }
}
