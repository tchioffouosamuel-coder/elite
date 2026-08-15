<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FonctionReferentielResource;
use App\Models\FonctionReferentiel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FonctionReferentielController extends Controller
{
    public function index(): JsonResponse
    {
        $fonctions = FonctionReferentiel::forSchool(app('tenant.school_id'))
            ->withCount('personnels')
            ->orderBy('label_fr')
            ->get();

        return ApiResponse::success(FonctionReferentielResource::collection($fonctions));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $schoolId = app('tenant.school_id');
        $data = $request->validate([
            'label_fr' => [
                'required',
                'string',
                'max:150',
                Rule::unique('fonction_referentiel', 'label_fr')->where('school_id', $schoolId),
            ],
            'label_en' => ['nullable', 'string', 'max:150'],
        ]);

        $fonction = FonctionReferentiel::create([...$data, 'school_id' => $schoolId]);

        return ApiResponse::created(new FonctionReferentielResource($fonction), 'Fonction créée.');
    }

    public function show(int $id): JsonResponse
    {
        $fonction = FonctionReferentiel::forSchool(app('tenant.school_id'))->withCount('personnels')->findOrFail($id);

        return ApiResponse::success(new FonctionReferentielResource($fonction));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $schoolId = app('tenant.school_id');
        $fonction = FonctionReferentiel::forSchool($schoolId)->findOrFail($id);

        $data = $request->validate([
            'label_fr' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('fonction_referentiel', 'label_fr')->where('school_id', $schoolId)->ignore($id),
            ],
            'label_en' => ['nullable', 'string', 'max:150'],
        ]);

        $fonction->update($data);

        return ApiResponse::success(new FonctionReferentielResource($fonction), 'Fonction mise à jour.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $fonction = FonctionReferentiel::forSchool(app('tenant.school_id'))
            ->withCount('personnels')
            ->findOrFail($id);

        if ($fonction->personnels_count > 0) {
            return ApiResponse::error('Cette fonction est utilisée par le personnel. Impossible de la supprimer.', 422);
        }

        $fonction->delete();

        return ApiResponse::success(null, 'Fonction supprimée.');
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $schoolId = app('tenant.school_id');
        $deleted = 0;
        $ignorees = [];

        foreach ($data['ids'] as $id) {
            $fonction = FonctionReferentiel::forSchool($schoolId)->withCount('personnels')->findOrFail($id);

            // Une fonction encore portée par du personnel ne peut pas disparaître
            // silencieusement au milieu d'un lot : on la laisse de côté et on le
            // signale, plutôt que d'échouer tout le lot ou de casser la référence.
            if ($fonction->personnels_count > 0) {
                $ignorees[] = $fonction->label_fr;

                continue;
            }

            $fonction->delete();
            $deleted++;
        }

        $message = "{$deleted} fonction(s) supprimée(s).";
        if ($ignorees !== []) {
            $message .= ' '.count($ignorees).' ignorée(s) car utilisée(s) par du personnel : '.implode(', ', $ignorees).'.';
        }

        return ApiResponse::success(['deleted' => $deleted, 'ignorees' => $ignorees], $message);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }
}
