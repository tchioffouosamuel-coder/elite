<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BanqueResource;
use App\Models\Banque;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\BanqueSpec;
use App\Services\BanqueService;
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
        $banques = Banque::query()
            ->withCount('personnels')
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(BanqueResource::collection($banques));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            // Compte de l'établissement dans cette banque — celui débité au
            // bordereau de virement des salaires, distinct du compte de
            // chaque agent.
            'numero_compte_ecole' => ['nullable', 'string', 'max:50'],
        ]);

        Validator::make($data, [
            'nom' => [Rule::unique('banques', 'nom')],
        ])->validate();

        $banque = Banque::create([
            'nom' => $data['nom'],
            'code' => $data['code'] ?? null,
            'numero_compte_ecole' => $data['numero_compte_ecole'] ?? null,
        ]);

        return ApiResponse::created(new BanqueResource($banque), 'Banque créée.');
    }

    public function show(int $id): JsonResponse
    {
        $banque = Banque::withCount('personnels')
            ->with(['mouvements' => fn($query) => $query->latest('date_mouvement')->latest('id')->limit(100)])
            ->findOrFail($id);

        return ApiResponse::success(new BanqueResource($banque));
    }

    public function deposer(Request $request, int $id, BanqueService $service): JsonResponse
    {
        return $this->mouvementManuel($request, $id, $service, 'depot');
    }

    public function retirer(Request $request, int $id, BanqueService $service): JsonResponse
    {
        return $this->mouvementManuel($request, $id, $service, 'retrait');
    }

    private function mouvementManuel(Request $request, int $id, BanqueService $service, string $type): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date' => ['nullable', 'date'],
            'libelle' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'cle_idempotence' => ['nullable', 'string', 'max:100'],
        ]);

        $libelle = $data['libelle'] ?? ($type === 'depot' ? 'Dépôt manuel' : 'Retrait manuel');
        $cleIdempotence = $data['cle_idempotence'] ?? $request->header('Idempotency-Key');
        $mouvement = $type === 'depot'
            ? $service->deposer($id, $data['montant'], $data['date'] ?? null, $libelle, $data['reference'] ?? null, $request->user()?->id, $cleIdempotence)
            : $service->retirer($id, $data['montant'], $data['date'] ?? null, $libelle, $data['reference'] ?? null, $request->user()?->id, $cleIdempotence);

        return ApiResponse::success([
            'mouvement' => $mouvement,
            'banque' => new BanqueResource($mouvement->banque()->withCount('personnels')->firstOrFail()),
        ], $type === 'depot' ? 'Dépôt enregistré.' : 'Retrait enregistré.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $banque = Banque::findOrFail($id);

        $data = $request->validate([
            'nom' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('banques', 'nom')->ignore($id),
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

        $banque = Banque::withCount('personnels')->findOrFail($id);

        if ($banque->personnels_count > 0 || $banque->mouvements()->exists()) {
            return ApiResponse::error('Cette banque est utilisée par du personnel ou possède des mouvements. Impossible de la supprimer.', 422);
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

        $deleted = 0;
        $ignorees = [];

        foreach ($data['ids'] as $id) {
            $banque = Banque::withCount('personnels')->findOrFail($id);

            if ($banque->personnels_count > 0 || $banque->mouvements()->exists()) {
                $ignorees[] = $banque->nom;

                continue;
            }

            $banque->delete();
            $deleted++;
        }

        $message = "{$deleted} banque(s) supprimée(s).";
        if ($ignorees !== []) {
            $message .= ' ' . count($ignorees) . ' ignorée(s) car utilisée(s) par du personnel ou porteuse(s) de mouvements : ' . implode(', ', $ignorees) . '.';
        }

        return ApiResponse::success(['deleted' => $deleted, 'ignorees' => $ignorees], $message);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }
}
