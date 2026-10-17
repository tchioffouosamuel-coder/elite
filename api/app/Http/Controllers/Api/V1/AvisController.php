<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Services\AvisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace d'évaluation (point 24) : tout utilisateur authentifié (parent,
 * enseignant, personnel) y dépose un avis libre sur son propre compte —
 * {@see AvisAdminController} fait la lecture côté administration.
 */
class AvisController extends Controller
{
    public function __construct(private readonly AvisService $service) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sujet' => ['nullable', 'string', 'max:150'],
            'contenu' => ['required', 'string', 'max:2000'],
            'note' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $avis = $this->service->creer($request->user(), $data['contenu'], $data['sujet'] ?? null, $data['note'] ?? null);

        return ApiResponse::created($avis, 'Avis envoyé.');
    }

    public function mesAvis(Request $request): JsonResponse
    {
        $avis = Avis::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'sujet', 'contenu', 'note', 'created_at']);

        return ApiResponse::success($avis);
    }
}
