<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Models\Appreciation;
use App\Services\AppreciationService;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\AppreciationSpec;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel d'appréciations de la maternelle : les niveaux que l'enseignante
 * coche, et les couleurs dont le bulletin remplit ses cases.
 *
 * L'école règle ses libellés, ses émojis, ses couleurs et leur ordre — l'ordre
 * fixant la position des colonnes sur le document.
 */
class AppreciationController extends Controller
{
    use GereImportExport;

    protected function specificationImportExport(): SpecificationModele
    {
        return new AppreciationSpec();
    }

    public function __construct(private readonly AppreciationService $service) {}

    public function index(): JsonResponse
    {
        $schoolIds = Tenant::schoolIds();

        // Doter les écoles de maternelle qui n'ont rien : « paramétrable » ne
        // veut pas dire « vide au départ ». Un référentiel déjà réglé n'est
        // jamais retouché.
        foreach ($schoolIds as $schoolId) {
            $this->service->assurerDefauts($schoolId);
        }

        $appreciations = Appreciation::forSchool($schoolIds)
            ->with('school:id,name,code,type')
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success($appreciations->map(fn (Appreciation $a) => $this->resumer($a))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $schoolId = Tenant::resolveWriteSchoolId($donnees['school_id'] ?? null);
        unset($donnees['school_id']);

        $appreciation = Appreciation::create([...$donnees, 'school_id' => $schoolId])->refresh();

        return ApiResponse::created($this->resumer($appreciation), 'Niveau ajouté.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $appreciation = Appreciation::forSchool(Tenant::schoolIds())->findOrFail($id);
        $donnees = $this->valider($request);
        unset($donnees['school_id']);

        $appreciation->update($donnees);

        return ApiResponse::success($this->resumer($appreciation->refresh()), 'Niveau mis à jour.');
    }

    /**
     * Un niveau déjà employé ne se supprime pas : les bulletins déjà remplis y
     * perdraient leur couleur. On le rend inactif — il disparaît des colonnes
     * sans effacer ce qui a été évalué.
     */
    public function destroy(int $id): JsonResponse
    {
        $appreciation = Appreciation::forSchool(Tenant::schoolIds())->findOrFail($id);
        $employe = $appreciation->notes()->count();

        if ($employe > 0) {
            return ApiResponse::error(
                "Ce niveau est employé sur {$employe} évaluation(s) : rendez-le inactif plutôt que de le supprimer.",
                422,
            );
        }

        $appreciation->delete();

        return ApiResponse::success(null, 'Niveau supprimé.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'label_fr' => ['required', 'string', 'max:100'],
            'label_en' => ['nullable', 'string', 'max:100'],
            // Un émoji tient en quelques octets UTF-8 ; on borne large.
            'emoji' => ['nullable', 'string', 'max:8'],
            'couleur' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ordre' => ['required', 'integer', 'min:1', 'max:20'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(Appreciation $appreciation): array
    {
        return [
            'id' => $appreciation->id,
            'label_fr' => $appreciation->label_fr,
            'label_en' => $appreciation->label_en,
            'emoji' => $appreciation->emoji,
            'couleur' => $appreciation->couleur,
            'ordre' => $appreciation->ordre,
            'statut' => $appreciation->statut,
            'school_id' => $appreciation->school_id,
            'school' => $appreciation->school ? [
                'id' => $appreciation->school->id,
                'name' => $appreciation->school->name,
                'type' => $appreciation->school->type,
            ] : null,
        ];
    }
}
