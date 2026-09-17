<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BanqueResource;
use App\Models\Banque;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\BanqueSpec;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BanqueController extends Controller
{
    use GereImportExport;

    protected function specificationImportExport(): SpecificationModele
    {
        return new BanqueSpec();
    }

    public function index(): JsonResponse
    {
        $banques = Banque::forSchool(Tenant::schoolIds())
            ->with('school:id,name,code,type')
            ->withCount('personnels')
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(BanqueResource::collection($banques));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            // Compte de l'établissement dans cette banque — celui débité au
            // bordereau de virement des salaires, distinct du compte de
            // chaque agent.
            'numero_compte_ecole' => ['nullable', 'string', 'max:50'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);

        Validator::make($data, [
            'nom' => [Rule::unique('banques', 'nom')->where('school_id', $schoolId)],
        ])->validate();

        $banque = Banque::create([
            'nom' => $data['nom'],
            'code' => $data['code'] ?? null,
            'numero_compte_ecole' => $data['numero_compte_ecole'] ?? null,
            'school_id' => $schoolId,
        ])->load('school:id,name,code,type');

        return ApiResponse::created(new BanqueResource($banque), 'Banque créée.');
    }

    public function show(int $id): JsonResponse
    {
        $banque = Banque::forSchool(Tenant::schoolIds())->withCount('personnels')->findOrFail($id);

        return ApiResponse::success(new BanqueResource($banque));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $banque = Banque::forSchool(Tenant::schoolIds())->findOrFail($id);
        $schoolId = $banque->school_id;

        $data = $request->validate([
            'nom' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('banques', 'nom')->where('school_id', $schoolId)->ignore($id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'numero_compte_ecole' => ['nullable', 'string', 'max:50'],
        ]);

        $banque->update($data);

        return ApiResponse::success(new BanqueResource($banque), 'Banque mise à jour.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $banque = Banque::forSchool(Tenant::schoolIds())
            ->withCount('personnels')
            ->findOrFail($id);

        if ($banque->personnels_count > 0) {
            return ApiResponse::error('Cette banque est utilisée par du personnel. Impossible de la supprimer.', 422);
        }

        $banque->delete();

        return ApiResponse::success(null, 'Banque supprimée.');
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
            $banque = Banque::forSchool($schoolIds)->withCount('personnels')->findOrFail($id);

            if ($banque->personnels_count > 0) {
                $ignorees[] = $banque->nom;

                continue;
            }

            $banque->delete();
            $deleted++;
        }

        $message = "{$deleted} banque(s) supprimée(s).";
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
