<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RemiseResource;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Remise;
use App\Services\ScolariteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Remises individuelles sur la scolarité — plusieurs lignes motivées par élève et par année. */
class RemiseController extends Controller
{
    public function __construct(private readonly ScolariteService $service) {}

    public function index(int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $remises = Remise::where('eleve_id', $eleve->id)
            ->with(['anneeScolaire:id,libelle', 'accordePar:id,name'])
            ->latest('id')
            ->get();

        return ApiResponse::success(RemiseResource::collection($remises));
    }

    public function store(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $data = $request->validate([
            // Optionnel : la liste des années scolaires exige `ecoles.manage`,
            // que le caissier qui accorde la remise n'a pas forcément. Sans
            // précision, c'est l'année active de l'école de l'élève.
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annee_scolaires,id'],
            'montant' => ['required', 'integer', 'min:1'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $annee = isset($data['annee_scolaire_id'])
            ? AnneeScolaire::where('school_id', $eleve->school_id)->findOrFail($data['annee_scolaire_id'])
            : AnneeScolaire::where('school_id', $eleve->school_id)->where('is_active', true)->firstOrFail();

        try {
            $remise = $this->service->enregistrerRemise($eleve, $annee, $data['montant'], $data['motif'] ?? null, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $remise->load(['anneeScolaire:id,libelle', 'accordePar:id,name']);

        return ApiResponse::created(new RemiseResource($remise), 'Remise accordée.');
    }

    public function destroy(int $id): JsonResponse
    {
        $remise = Remise::forSchool(Tenant::schoolIds())->findOrFail($id);
        $this->service->supprimerRemise($remise);

        return ApiResponse::success(null, 'Remise retirée.');
    }
}
