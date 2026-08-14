<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ClassementExport;
use App\Exports\PalmaresExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\School;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Services\MoyenneService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ResultatController extends Controller
{
    public function __construct(private readonly MoyenneService $service) {}

    public function remplissage(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = $this->resolveTrimestre($request);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'matieres' => $this->service->remplissageClasse($classe, $trimestre),
        ]);
    }

    public function classement(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = $this->resolveTrimestre($request);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'eleves' => $this->classementRows($classe, $trimestre),
        ]);
    }

    public function exportClassement(Request $request, int $classeId): BinaryFileResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = $this->resolveTrimestre($request);

        return Excel::download(
            new ClassementExport($this->classementRows($classe, $trimestre)),
            "classement-{$classe->nom}-{$trimestre->libelle}.xlsx"
        );
    }

    public function palmares(Request $request): JsonResponse
    {
        [$trimestre, $rows] = $this->palmaresRows($request);

        $eleves = $rows->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nom_complet,
            'classe' => $row['eleve']->classe?->nom,
            'moyenne' => $row['moyenne'],
            'heures_non_justifiees' => $row['heures_non_justifiees'],
        ]);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'eleves' => $eleves,
        ]);
    }

    public function exportPalmares(Request $request): BinaryFileResponse
    {
        [$trimestre, $rows] = $this->palmaresRows($request);

        return Excel::download(new PalmaresExport($rows), "palmares-{$trimestre->libelle}.xlsx");
    }

    public function palmaresPdf(Request $request): Response
    {
        [$trimestre, $rows] = $this->palmaresRows($request);
        $schoolId = app('tenant.school_id');
        $classeId = $request->integer('classe_id') ?: null;

        $pdf = Pdf::loadView('pdf.palmares', [
            'school' => School::findOrFail($schoolId),
            'trimestre' => $trimestre,
            'eleves' => $rows,
            'classeLabel' => $classeId ? Classe::find($classeId)?->nom : null,
            'seuilMoyenne' => Setting::get($schoolId, 'honour_roll', 14),
            'seuilAbsences' => Setting::get($schoolId, 'honour_attendance_max', 20),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("palmares-{$trimestre->libelle}.pdf");
    }

    /** @return Collection<int, array<string, mixed>> */
    private function classementRows(Classe $classe, Trimestre $trimestre): Collection
    {
        $schoolId = app('tenant.school_id');

        return $this->service->classementGeneral($classe, $trimestre)->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nom_complet,
            'moyenne' => $row['moyenne'],
            'rang' => $row['rang'],
            'cote' => $this->service->lettreCote($row['moyenne']),
            'mention' => $this->service->mentionTravail($schoolId, $row['moyenne']),
        ]);
    }

    /** @return array{0: Trimestre, 1: Collection} */
    private function palmaresRows(Request $request): array
    {
        $trimestre = $this->resolveTrimestre($request);
        $classeId = $request->integer('classe_id') ?: null;

        if ($classeId) {
            Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        }

        return [$trimestre, $this->service->palmares(app('tenant.school_id'), $trimestre, $classeId)];
    }

    private function resolveTrimestre(Request $request): Trimestre
    {
        $schoolId = app('tenant.school_id');
        $query = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId));

        if ($trimestreId = $request->integer('trimestre_id')) {
            return $query->findOrFail($trimestreId);
        }

        return $query->where('is_active', true)->firstOrFail();
    }
}
