<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DetteAnterieureResource;
use App\Models\DetteAnterieure;
use App\Models\Eleve;
use App\Models\School;
use App\Services\ScolariteService;
use App\Support\Pdf\DettesAnterieuresGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dettes des années antérieures, saisies à la main — le cas d'un élève qui
 * doit déjà de l'argent avant l'ouverture de son premier dossier dans ce
 * système. Imputées automatiquement au `report_dette` du dossier de
 * l'année active dès que possible (cf. ScolariteService::enregistrerDetteAnterieure()).
 */
class DetteAnterieureController extends Controller
{
    public function __construct(private readonly ScolariteService $service) {}

    public function index(int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $dettes = DetteAnterieure::where('eleve_id', $eleve->id)
            ->with('accordePar:id,name')
            ->latest('id')
            ->get();

        return ApiResponse::success(DetteAnterieureResource::collection($dettes));
    }

    /**
     * Tous les reliquats d'années antérieures non soldés, un ou plusieurs
     * établissements confondus — la vue caisse d'ensemble, distincte de la
     * fiche par élève ci-dessus.
     */
    public function liste(Request $request): JsonResponse
    {
        $situation = $this->service->dettesAnterieures($this->schoolIds($request), [
            'classe_id' => $request->integer('classe_id') ?: null,
        ]);

        return ApiResponse::success($situation);
    }

    public function pdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $situation = $this->service->dettesAnterieures($schoolIds, [
            'classe_id' => $request->integer('classe_id') ?: null,
        ]);

        $school = School::whereIn('id', $schoolIds)->first();

        $pdf = (new DettesAnterieuresGenerator)->build($school, $situation['lignes'], $situation['totaux']);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="dettes-anterieures.pdf"',
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

    public function store(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dette = $this->service->enregistrerDetteAnterieure($eleve, $data['montant'], $data['motif'] ?? null, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $dette->load('accordePar:id,name');

        return ApiResponse::created(new DetteAnterieureResource($dette), 'Dette antérieure enregistrée.');
    }

    public function destroy(int $id): JsonResponse
    {
        $dette = DetteAnterieure::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $this->service->supprimerDetteAnterieure($dette);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Dette antérieure retirée.');
    }

    /**
     * Efface le reliquat d'années antérieures de l'élève — geste distinct de
     * `destroy()` : celui-ci ne retire qu'une dette isolée pas encore
     * imputée, `oublier()` dédouane l'élève de tout reliquat déjà repris
     * dans son dossier de l'année active.
     */
    public function oublier(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        try {
            $this->service->oublierDetteAnterieure($eleve, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Reliquat effacé.');
    }
}
