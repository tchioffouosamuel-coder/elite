<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AvisResource;
use App\Models\Avis;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lecture et filtrage, côté administration, des avis déposés par les
 * parents, enseignants et personnels (espace d'évaluation, point 24).
 */
class AvisAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'role' => ['nullable', 'string'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'note_min' => ['nullable', 'integer', 'min:1', 'max:5'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $avis = Avis::forSchool(Tenant::schoolIds())
            ->with('user:id,name')
            ->when(
                $filtres['role'] ?? null,
                fn ($q, $role) => $q->whereHas('user.roles', fn ($r) => $r->where('name', $role)),
            )
            ->when($filtres['date_debut'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filtres['date_fin'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filtres['note_min'] ?? null, fn ($q, $note) => $q->where('note', '>=', $note))
            ->orderByDesc('created_at')
            ->paginate($filtres['par_page'] ?? 20);

        return ApiResponse::paginated($avis, AvisResource::class);
    }
}
