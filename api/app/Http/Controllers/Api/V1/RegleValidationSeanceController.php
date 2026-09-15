<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RegleValidationSeanceResource;
use App\Models\RegleValidationSeance;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Règles de preuve de présence pour « Ma journée » (méthode + délai de
 * correction), définies par la direction par école et, en option, par
 * sous-système — cf. `RegleValidationSeance`, `Seance::minutesVerrouillageAppel()`
 * et `User::methodeValidationSeance()`.
 */
class RegleValidationSeanceController extends Controller
{
    public function index(): JsonResponse
    {
        $regles = RegleValidationSeance::forSchool(Tenant::schoolIds())
            ->with('sousSysteme:id,nom')
            ->orderBy('school_id')
            ->get();

        return ApiResponse::success(RegleValidationSeanceResource::collection($regles));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $this->valider($request);
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        $this->assurerUnicite($data, $schoolId, null);

        $regle = RegleValidationSeance::create([
            ...$data,
            'school_id' => $schoolId,
        ])->load('sousSysteme:id,nom');

        return ApiResponse::created(new RegleValidationSeanceResource($regle), 'Règle créée.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $regle = RegleValidationSeance::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $this->valider($request);

        $this->assurerUnicite($data, $regle->school_id, $id);

        $regle->update($data);

        return ApiResponse::success(new RegleValidationSeanceResource($regle->load('sousSysteme:id,nom')), 'Règle mise à jour.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $regle = RegleValidationSeance::forSchool(Tenant::schoolIds())->findOrFail($id);
        $regle->delete();

        return ApiResponse::success(null, 'Règle supprimée.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'sous_systeme_id' => ['nullable', 'integer', 'exists:sous_systemes,id'],
            'methode_validation' => ['required', 'in:qr,code,libre'],
            'delai_valeur' => ['required', 'integer', 'min:1', 'max:9999'],
            'delai_unite' => ['required', 'in:minutes,jours,semaines'],
        ]);
    }

    /** Une seule règle par périmètre (école + sous-système, ou école seule). */
    private function assurerUnicite(array $data, int $schoolId, ?int $ignoreId): void
    {
        $existe = RegleValidationSeance::where('school_id', $schoolId)
            ->where('sous_systeme_id', $data['sous_systeme_id'] ?? null)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        Validator::make([], [])->after(function ($validator) use ($existe) {
            if ($existe) {
                $validator->errors()->add('sous_systeme_id', 'Une règle existe déjà pour ce périmètre.');
            }
        })->validate();
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }
}
