<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAnneeScolaireRequest;
use App\Http\Requests\Api\V1\UpdateAnneeScolaireRequest;
use App\Http\Resources\Api\V1\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Services\ArchivageService;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnneeScolaireController extends Controller
{
    public function index(): JsonResponse
    {
        $annees = AnneeScolaire::where('school_id', app('tenant.school_id'))->orderByDesc('date_debut')->get();

        return ApiResponse::success(AnneeScolaireResource::collection($annees));
    }

    public function store(StoreAnneeScolaireRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $data = $request->validated();

        $annee = DB::transaction(function () use ($schoolId, $data) {
            if ($data['is_active'] ?? false) {
                AnneeScolaire::where('school_id', $schoolId)->update(['is_active' => false]);
            }

            return AnneeScolaire::create([...$data, 'school_id' => $schoolId]);
        });

        return ApiResponse::created(new AnneeScolaireResource($annee), 'Année scolaire créée.');
    }

    public function activate(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        DB::transaction(function () use ($schoolId, $annee) {
            AnneeScolaire::where('school_id', $schoolId)->update(['is_active' => false]);
            $annee->update(['is_active' => true]);
        });

        return ApiResponse::success(new AnneeScolaireResource($annee->refresh()), 'Année scolaire activée.');
    }

    public function update(int $id, UpdateAnneeScolaireRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        $annee->update($request->validated());

        return ApiResponse::success(new AnneeScolaireResource($annee->refresh()), 'Année scolaire mise à jour.');
    }

    /**
     * Archive l'année : exige un conseil de classe validé pour chaque classe
     * non vide (sinon 422, avec la liste des classes manquantes), fige leurs
     * données pédagogiques, puis pose `archivee_le`. Voir ArchivageService.
     */
    public function archiver(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        try {
            app(ArchivageService::class)->archiverAnnee($annee, auth()->id());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new AnneeScolaireResource($annee->refresh()), 'Année archivée.');
    }

    /**
     * Passe à l'année suivante : l'année active doit être archivée, et une
     * année scolaire postérieure doit déjà exister (créée à l'avance par
     * l'établissement) — ce n'est qu'une bascule, pas une création.
     */
    public function basculer(int $id, Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $actuelle = AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail();

        if ($actuelle->archivee_le === null) {
            return ApiResponse::error("L'année active doit être archivée avant de basculer vers la suivante.", 422);
        }

        $suivante = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        if ($suivante->date_debut->lte($actuelle->date_debut)) {
            return ApiResponse::error("L'année choisie n'est pas postérieure à l'année active.", 422);
        }

        DB::transaction(function () use ($schoolId, $suivante) {
            AnneeScolaire::where('school_id', $schoolId)->update(['is_active' => false]);
            $suivante->update(['is_active' => true]);
        });

        return ApiResponse::success(new AnneeScolaireResource($suivante->refresh()), 'Année scolaire suivante activée.');
    }

    /** Matérialise les séances de toutes les classes de l'année en un coup. */
    public function genererSeances(int $id, Request $request, EmploiDuTempsService $service): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);

        // Une classe est un gabarit permanent de l'établissement (cf. migration
        // 2026_08_23_150000), pas rattaché à une année précise : « les classes
        // de cette année » sont donc celles de l'école, bornées au périmètre.
        $classes = Classe::forSchool($schoolId)
            ->dansPerimetre($request->user())
            ->get();

        $resultat = $service->genererSeancesPourClasses($classes, $annee->date_debut, $annee->date_fin, null);

        return ApiResponse::success($resultat, "{$resultat['creees']} séance(s) générée(s) sur {$resultat['classes']} classe(s).");
    }
}
