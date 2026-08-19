<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\BusVehicule;
use App\Services\BusService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusVehiculeController extends Controller
{
    use ScopedRules;

    public function __construct(private readonly BusService $service) {}

    public function index(): JsonResponse
    {
        $vehicules = $this->service->listerVehicules(Tenant::schoolIds());

        return ApiResponse::success($vehicules->map(fn (BusVehicule $v) => $this->resumer($v))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $schoolId = Tenant::resolveWriteSchoolId($donnees['school_id'] ?? null);
        unset($donnees['school_id']);

        $vehicule = $this->service->creerVehicule($schoolId, $donnees);

        return ApiResponse::created($this->resumer($vehicule->load('school:id,name,code,type')), 'Véhicule ajouté.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicule = $this->vehicule($id);
        $donnees = $this->valider($request, $vehicule->id, $vehicule->school_id);
        unset($donnees['school_id']);

        $vehicule = $this->service->modifierVehicule($vehicule, $donnees);

        return ApiResponse::success($this->resumer($vehicule->load('school:id,name,code,type')), 'Véhicule mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->supprimerVehicule($this->vehicule($id));

        return ApiResponse::success(null, 'Véhicule supprimé.');
    }

    /** @return array<string, mixed> */
    private function resumer(BusVehicule $vehicule): array
    {
        return [
            'id' => $vehicule->id,
            'immatriculation' => $vehicule->immatriculation,
            'marque' => $vehicule->marque,
            'capacite' => $vehicule->capacite,
            'statut' => $vehicule->statut,
            'chauffeur' => $vehicule->chauffeur ? [
                'id' => $vehicule->chauffeur->id,
                'nom_complet' => $vehicule->chauffeur->nom_complet,
                'telephone' => $vehicule->chauffeur->telephone,
            ] : null,
            'school' => $vehicule->school ? [
                'id' => $vehicule->school->id,
                'name' => $vehicule->school->name,
                'code' => $vehicule->school->code,
                'type' => $vehicule->school->type,
            ] : null,
        ];
    }

    private function vehicule(int $id): BusVehicule
    {
        return BusVehicule::forSchool(Tenant::schoolIds())->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?int $ignorerId = null, ?int $schoolIdConnu = null): array
    {
        // À la création, l'unicité de l'immatriculation se vérifie dans
        // l'école soumise (si le super admin en précise une en mode agrégé) ;
        // à la modification, dans celle — fixe — du véhicule.
        $schoolId = $schoolIdConnu ?? ($request->integer('school_id') ?: app('tenant.school_id'));

        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'immatriculation' => [
                'required', 'string', 'max:20',
                Rule::unique('bus_vehicules', 'immatriculation')->where('school_id', $schoolId)->ignore($ignorerId),
            ],
            'marque' => ['nullable', 'string', 'max:100'],
            'capacite' => ['nullable', 'integer', 'min:1', 'max:200'],
            'chauffeur_id' => ['nullable', 'integer', $this->scopedExists('personnels')],
            'statut' => ['nullable', 'in:actif,hors_service'],
        ]);
    }
}
