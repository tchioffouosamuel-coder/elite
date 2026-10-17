<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AbsenceModeleExport;
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
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    /** Modèle vide (matricule + nom déjà remplis) à téléverser via import(). */
    public function modele(int $classeId): BinaryFileResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);

        abort_if(
            ! $classe->school->estSecondaire(),
            422,
            "Au primaire et en maternelle, l'assiduité se déduit des appels : il n'y a rien à importer ici."
        );

        $nomFichier = 'modele-absences-'.Str::slug($classe->nom).'.xlsx';

        return Excel::download(new AbsenceModeleExport($this->service->lignesModele($classe)), $nomFichier);
    }

    public function import(Request $request, int $classeId): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv'], 'trimestre_id' => ['required', 'integer']]);

        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('trimestre_id'));

        $result = $this->service->importerAbsencesDepuisExcel(
            $classe->school_id, $classe, $trimestre, $request->file('file'), $request->user(),
            $request->boolean('confirmer_remplacement'),
        );

        // Au moins une ligne valide remplacerait une correction déjà
        // saisie et différente : rien n'a été écrit, l'écran doit faire
        // confirmer explicitement avant de renvoyer la même requête.
        if ($result['confirmation_requise']) {
            return ApiResponse::error(
                'Cet import remplacerait des corrections déjà saisies : ajoutez confirmer_remplacement=1 pour confirmer.',
                409,
                ['conflits' => $result['conflits'], 'failed' => $result['failed'], 'errors' => $result['errors']],
            );
        }

        return ApiResponse::success($result, "{$result['imported']} correction(s) importée(s).");
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
