<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DetteAnterieureResource;
use App\Models\DetteAnterieure;
use App\Models\Eleve;
use App\Services\ScolariteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
}
