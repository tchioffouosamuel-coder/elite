<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RemiseResource;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Remise;
use App\Models\School;
use App\Services\ScolariteService;
use App\Support\Pdf\RemisesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Remises individuelles sur la scolarité — plusieurs lignes motivées par élève et par année. */
class RemiseController extends Controller
{
    public function __construct(private readonly ScolariteService $service) {}

    /**
     * Export PDF des remises accordées sur le périmètre visible (toutes
     * écoles du tenant, ou une seule si précisée), filtrable par classe et
     * par année scolaire — sans filtre d'année, celles actives par école.
     */
    public function pdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $classeId = $request->integer('classe_id') ?: null;
        $anneeId = $request->integer('annee_scolaire_id') ?: null;

        $anneeIds = $anneeId
            ? [$anneeId]
            : AnneeScolaire::whereIn('school_id', $schoolIds)->where('is_active', true)->pluck('id')->all();

        $remises = Remise::forSchool($schoolIds)
            ->whereIn('annee_scolaire_id', $anneeIds)
            ->when($classeId, fn($q, $classeId) => $q->whereHas('eleve', fn($eq) => $eq->where('classe_id', $classeId)))
            ->with(['eleve.classe', 'accordePar'])
            ->orderBy('created_at')
            ->get();

        $school = School::whereIn('id', $schoolIds)->first();

        $pdf = (new RemisesGenerator)->build($school, $remises);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-remises.pdf"',
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

    public function index(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->findOrFail($eleveId);

        $remises = Remise::where('eleve_id', $eleve->id)
            ->with(['anneeScolaire:id,libelle', 'accordePar:id,name'])
            ->latest('id')
            ->get();

        return ApiResponse::success(RemiseResource::collection($remises));
    }

    public function store(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->findOrFail($eleveId);

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

    public function update(Request $request, int $id): JsonResponse
    {
        $remise = Remise::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $remise->update([
                'montant' => $data['montant'],
                'motif' => $data['motif'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $remise->load(['anneeScolaire:id,libelle', 'accordePar:id,name']);

        return ApiResponse::success(new RemiseResource($remise), 'Remise modifiée.');
    }
}
