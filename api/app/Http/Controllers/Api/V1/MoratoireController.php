<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MoratoireResource;
use App\Models\Eleve;
use App\Models\Moratoire;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moratoires sur la scolarité : le délai accordé à une famille avant d'être
 * comptée parmi les insolvables. Rattachés à l'élève, jamais à un dossier
 * d'année précise — un moratoire couvre une période calendaire, indépendante
 * de l'année scolaire en cours au moment où il est accordé.
 */
class MoratoireController extends Controller
{
    /** Historique des moratoires d'un élève, le plus récent en tête. */
    public function index(int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $moratoires = Moratoire::where('eleve_id', $eleve->id)
            ->with('accordePar:id,name')
            ->latest('date_delivrance')
            ->get();

        return ApiResponse::success(MoratoireResource::collection($moratoires));
    }

    public function store(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $data = $request->validate([
            'date_delivrance' => ['required', 'date'],
            'date_expiration' => ['required', 'date', 'after_or_equal:date_delivrance'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $moratoire = Moratoire::create([
            ...$data,
            'school_id' => $eleve->school_id,
            'eleve_id' => $eleve->id,
            'accorde_par' => $request->user()?->id,
        ]);
        $moratoire->load('accordePar:id,name');

        return ApiResponse::created(new MoratoireResource($moratoire), 'Moratoire accordé.');
    }

    public function destroy(int $id): JsonResponse
    {
        $moratoire = Moratoire::forSchool(Tenant::schoolIds())->findOrFail($id);
        $moratoire->delete();

        return ApiResponse::success(null, 'Moratoire retiré.');
    }
}
