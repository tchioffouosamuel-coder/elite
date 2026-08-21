<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVisiteInfirmerieRequest;
use App\Http\Requests\Api\V1\UpdateVisiteInfirmerieRequest;
use App\Http\Resources\Api\V1\VisiteInfirmerieResource;
use App\Models\Eleve;
use App\Models\VisiteInfirmerie;
use App\Services\InfirmerieService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisiteInfirmerieController extends Controller
{
    private const AVEC_RELATIONS = ['eleve.school', 'classe', 'enregistrePar', 'malaises', 'materiels.article'];

    public function __construct(private readonly InfirmerieService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'eleve_id' => ['nullable', 'integer'],
            'classe_id' => ['nullable', 'integer'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $visites = VisiteInfirmerie::forSchool(Tenant::schoolIds())
            ->with(self::AVEC_RELATIONS)
            ->when($request->integer('eleve_id'), fn ($q, $id) => $q->where('eleve_id', $id))
            ->when($request->integer('classe_id'), fn ($q, $id) => $q->where('classe_id', $id))
            ->when($request->string('du')->toString(), fn ($q, $du) => $q->whereDate('date_visite', '>=', $du))
            ->when($request->string('au')->toString(), fn ($q, $au) => $q->whereDate('date_visite', '<=', $au))
            ->latest('date_visite')
            ->get();

        return ApiResponse::success(VisiteInfirmerieResource::collection($visites));
    }

    public function store(StoreVisiteInfirmerieRequest $request): JsonResponse
    {
        $eleve = $this->eleve($request->integer('eleve_id'));
        $donnees = $request->validated();

        $visite = $this->service->creer(
            [
                ...collect($donnees)->except(['malaise_ids', 'materiels'])->all(),
                'classe_id' => $eleve->classe_id,
                'structure_externe' => $donnees['type_traitement'] === 'interne' ? null : ($donnees['structure_externe'] ?? null),
                'cout_soins' => $request->integer('cout_soins'),
                'cout_autre_materiel' => $request->integer('cout_autre_materiel'),
                'enregistre_par' => $request->user()->personnel?->id,
            ],
            $donnees['malaise_ids'] ?? [],
            $donnees['materiels'] ?? [],
        );

        return ApiResponse::created(
            new VisiteInfirmerieResource($visite->load(self::AVEC_RELATIONS)),
            'Visite à l’infirmerie enregistrée.'
        );
    }

    public function update(UpdateVisiteInfirmerieRequest $request, int $id): JsonResponse
    {
        $visite = $this->visite($id);
        $eleve = $this->eleve($request->integer('eleve_id'));
        $donnees = $request->validated();

        $visite = $this->service->modifier(
            $visite,
            [
                ...collect($donnees)->except(['malaise_ids', 'materiels'])->all(),
                'classe_id' => $eleve->classe_id,
                'structure_externe' => $donnees['type_traitement'] === 'interne' ? null : ($donnees['structure_externe'] ?? null),
                'cout_soins' => $request->integer('cout_soins'),
                'cout_autre_materiel' => $request->integer('cout_autre_materiel'),
            ],
            $donnees['malaise_ids'] ?? [],
            $donnees['materiels'] ?? [],
        );

        return ApiResponse::success(
            new VisiteInfirmerieResource($visite->load(self::AVEC_RELATIONS)),
            'Visite à l’infirmerie mise à jour.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->supprimer($this->visite($id));

        return ApiResponse::success(message: 'Visite à l’infirmerie supprimée.');
    }

    private function visite(int $id): VisiteInfirmerie
    {
        return VisiteInfirmerie::forSchool(Tenant::schoolIds())->findOrFail($id);
    }

    private function eleve(int $id): Eleve
    {
        return Eleve::forSchool(Tenant::schoolIds())->findOrFail($id);
    }
}
