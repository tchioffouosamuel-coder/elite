<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FonctionReferentielResource;
use App\Models\FonctionReferentiel;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FonctionReferentielController extends Controller
{
    public function index(): JsonResponse
    {
        $fonctions = FonctionReferentiel::forSchool(Tenant::schoolIds())
            ->with('school:id,name,code,type')
            ->withCount('personnels')
            ->orderBy('label_fr')
            ->get();

        return ApiResponse::success(FonctionReferentielResource::collection($fonctions));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'label_fr' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        Validator::make($data, [
            'label_fr' => [Rule::unique('fonction_referentiel', 'label_fr')->where('school_id', $schoolId)],
        ])->validate();

        $fonction = FonctionReferentiel::create([
            'label_fr' => $data['label_fr'],
            'label_en' => $data['label_en'] ?? null,
            'school_id' => $schoolId,
        ])->load('school:id,name,code,type');

        return ApiResponse::created(new FonctionReferentielResource($fonction), 'Fonction créée.');
    }

    public function show(int $id): JsonResponse
    {
        $fonction = FonctionReferentiel::forSchool(Tenant::schoolIds())->withCount('personnels')->findOrFail($id);

        return ApiResponse::success(new FonctionReferentielResource($fonction));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $fonction = FonctionReferentiel::forSchool(Tenant::schoolIds())->findOrFail($id);
        $schoolId = $fonction->school_id;

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

        $fonction = FonctionReferentiel::forSchool(Tenant::schoolIds())
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

        $schoolIds = Tenant::schoolIds();
        $deleted = 0;
        $ignorees = [];

        foreach ($data['ids'] as $id) {
            $fonction = FonctionReferentiel::forSchool($schoolIds)->withCount('personnels')->findOrFail($id);

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
