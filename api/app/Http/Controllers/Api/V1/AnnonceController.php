<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\User;
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
            'cible_type' => ['nullable', 'string', 'in:tous,roles,utilisateurs'],
            'cible' => ['required_unless:cible_type,tous', 'array'],
            'cible.*' => ['string'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        $cibleType = $data['cible_type'] ?? 'tous';
        $cible = $data['cible'] ?? [];

        $annonce = Annonce::create([
            'school_id' => $schoolId,
            'titre' => $data['titre'],
            'contenu' => $data['contenu'],
            'publie_par' => $request->user()->personnel?->id,
            'publiee_le' => now(),
            'cible_type' => $cibleType,
            'cible_data' => $cibleType === 'tous' ? null : $cible,
        ]);

        // Le personnel voit l'annonce immédiatement dans sa cloche ; les
        // parents la découvrent, eux, dans le résumé hebdomadaire par SMS.
        // La permission `annonces.view` reste le garde-fou dans tous les cas :
        // cibler quelqu'un qui n'a pas le droit de voir les annonces ne doit
        // pas le faire sortir du contrôle d'accès.
        $userIds = match ($cibleType) {
            'roles' => User::where('school_id', $schoolId)->role($cible)->permission('annonces.view')->pluck('id'),
            'utilisateurs' => User::where('school_id', $schoolId)->whereIn('id', $cible)->permission('annonces.view')->pluck('id'),
            default => User::where('school_id', $schoolId)->permission('annonces.view')->pluck('id'),
        };

        $this->notifications->notifier(
            $schoolId,
            $userIds,
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
