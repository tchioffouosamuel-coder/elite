<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MalaiseReferentielResource;
use App\Models\MalaiseReferentiel;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Référentiel des malaises/symptômes que l'infirmier coche lors d'une visite. */
class MalaiseReferentielController extends Controller
{
    public function index(): JsonResponse
    {
        $malaises = MalaiseReferentiel::forSchool(Tenant::schoolIds())
            ->with('school:id,name')
            ->orderBy('label_fr')
            ->get();

        return ApiResponse::success(MalaiseReferentielResource::collection($malaises));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'label_fr' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        Validator::make($data, [
            'label_fr' => [Rule::unique('malaises_referentiel', 'label_fr')->where('school_id', $schoolId)],
        ])->validate();

        $malaise = MalaiseReferentiel::create([
            'label_fr' => $data['label_fr'],
            'label_en' => $data['label_en'] ?? null,
            'school_id' => $schoolId,
        ])->load('school:id,name');

        return ApiResponse::created(new MalaiseReferentielResource($malaise), 'Malaise ajouté au référentiel.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $malaise = MalaiseReferentiel::forSchool(Tenant::schoolIds())->findOrFail($id);

        $data = $request->validate([
            'label_fr' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('malaises_referentiel', 'label_fr')->where('school_id', $malaise->school_id)->ignore($id),
            ],
            'label_en' => ['nullable', 'string', 'max:150'],
        ]);

        $malaise->update($data);

        return ApiResponse::success(new MalaiseReferentielResource($malaise), 'Malaise mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $malaise = MalaiseReferentiel::forSchool(Tenant::schoolIds())->findOrFail($id);
        $malaise->delete();

        return ApiResponse::success(null, 'Malaise supprimé du référentiel.');
    }
}
