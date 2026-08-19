<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annonces de l'établissement : publiées par l'administration, visibles de
 * tout le personnel et reprises dans le rapport hebdomadaire envoyé aux
 * parents (cf. `RapportHebdomadaireParents`).
 */
class AnnonceController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $annonces = Annonce::forSchool(Tenant::schoolIds())
            ->with(['publiePar', 'school:id,name,code,type'])
            ->orderByDesc('publiee_le')
            ->paginate(20);

        return ApiResponse::paginated($annonces);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:200'],
            'contenu' => ['required', 'string', 'max:2000'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        $annonce = Annonce::create([
            'school_id' => $schoolId,
            'titre' => $data['titre'],
            'contenu' => $data['contenu'],
            'publie_par' => $request->user()->personnel?->id,
            'publiee_le' => now(),
        ]);

        // Le personnel voit l'annonce immédiatement dans sa cloche ; les
        // parents la découvrent, eux, dans le résumé hebdomadaire par SMS.
        $this->notifications->notifierParPermission(
            $schoolId,
            'annonces.view',
            'annonce',
            $annonce->titre,
            $annonce->contenu,
        );

        return ApiResponse::created($annonce->load(['publiePar', 'school:id,name,code,type']), 'Annonce publiée.');
    }

    public function destroy(int $id): JsonResponse
    {
        $annonce = Annonce::forSchool(Tenant::schoolIds())->findOrFail($id);
        $annonce->delete();

        return ApiResponse::success();
    }
}
