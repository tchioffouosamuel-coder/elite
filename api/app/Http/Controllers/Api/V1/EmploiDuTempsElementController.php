<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\EmploiDuTempsElement;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploiDuTempsElementController extends Controller
{
    public function index(): JsonResponse
    {
        $elements = EmploiDuTempsElement::forSchool(Tenant::schoolIds())
            ->with('classes:id,nom,school_id')
            ->orderBy('type')->orderBy('heure_debut')->get();

        return ApiResponse::success($elements->map(fn(EmploiDuTempsElement $element) => $this->presenter($element)));
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = (int) app('tenant.school_id');
        $data = $this->valider($request, $schoolId);
        $classes = Classe::where('school_id', $schoolId)->whereIn('id', $data['classe_ids'])->get();

        $element = DB::transaction(function () use ($data, $schoolId, $classes) {
            $element = EmploiDuTempsElement::create([
                'school_id' => $schoolId,
                'type' => $data['type'],
                'nom' => $data['nom'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'jours' => $data['jours'],
            ]);
            $element->classes()->sync($classes->pluck('id'));
            $this->appliquerElement($element, $classes);

            return $element->load('classes:id,nom,school_id');
        });

        return ApiResponse::created($this->presenter($element), 'Élément ajouté aux emplois du temps.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $element = EmploiDuTempsElement::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $this->valider($request, $element->school_id);
        $classes = Classe::where('school_id', $element->school_id)->whereIn('id', $data['classe_ids'])->get();

        DB::transaction(function () use ($element, $data, $classes) {
            $element->update([
                'type' => $data['type'],
                'nom' => $data['nom'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'jours' => $data['jours'],
            ]);
            $element->classes()->sync($classes->pluck('id'));
            EmploiDuTemps::where('emploi_du_temps_element_id', $element->id)->delete();
            $this->appliquerElement($element->fresh(), $classes);
        });

        return ApiResponse::success($this->presenter($element->fresh('classes')), 'Élément mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $element = EmploiDuTempsElement::forSchool(Tenant::schoolIds())->findOrFail($id);
        $element->delete();

        return ApiResponse::success(message: 'Élément supprimé.');
    }

    public function apply(int $id): JsonResponse
    {
        $element = EmploiDuTempsElement::forSchool(Tenant::schoolIds())->with('classes')->findOrFail($id);
        $count = $this->appliquerElement($element, $element->classes);

        return ApiResponse::success(['creees' => $count], "{$count} créneau(x) généré(s).");
    }

    /** @return array{type:string,nom:string,heure_debut:string,heure_fin:string,jours:list<int>,classe_ids:list<int>} */
    private function valider(Request $request, int $schoolId): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:pause,activite'],
            'nom' => ['required', 'string', 'max:150'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'jours' => ['required', 'array', 'min:1'],
            'jours.*' => ['integer', 'min:1', 'max:7'],
            'classe_ids' => ['required', 'array', 'min:1'],
            'classe_ids.*' => ['integer', 'distinct'],
        ]);

        $valides = Classe::where('school_id', $schoolId)->whereIn('id', $data['classe_ids'])->pluck('id');
        abort_unless($valides->count() === count($data['classe_ids']), 422, "Une classe n'appartient pas à cet établissement.");

        $data['jours'] = array_values(array_unique(array_map('intval', $data['jours'])));
        $data['classe_ids'] = array_map('intval', $data['classe_ids']);

        return $data;
    }

    private function appliquerElement(EmploiDuTempsElement $element, iterable $classes): int
    {
        $crees = 0;
        foreach ($classes as $classe) {
            foreach ($element->jours as $jour) {
                $existe = EmploiDuTemps::where('emploi_du_temps_element_id', $element->id)
                    ->where('classe_id', $classe->id)->where('jour', $jour)->exists();
                if ($existe) continue;

                $chevauche = EmploiDuTemps::where('classe_id', $classe->id)
                    ->where('jour', $jour)
                    ->where('heure_debut', '<', $element->heure_fin)
                    ->where('heure_fin', '>', $element->heure_debut)
                    ->exists();
                if ($chevauche) continue;

                EmploiDuTemps::create([
                    'school_id' => $classe->school_id,
                    'classe_id' => $classe->id,
                    'classe_matiere_id' => null,
                    'type' => $element->type,
                    'libelle' => $element->nom,
                    'emploi_du_temps_element_id' => $element->id,
                    'jour' => $jour,
                    'heure_debut' => $element->heure_debut,
                    'heure_fin' => $element->heure_fin,
                ]);
                $crees++;
            }
        }

        return $crees;
    }

    private function presenter(EmploiDuTempsElement $element): array
    {
        return [
            'id' => $element->id,
            'type' => $element->type,
            'nom' => $element->nom,
            'heure_debut' => substr((string) $element->heure_debut, 0, 5),
            'heure_fin' => substr((string) $element->heure_fin, 0, 5),
            'jours' => $element->jours,
            'actif' => $element->actif,
            'classes' => $element->classes->map(fn(Classe $classe) => ['id' => $classe->id, 'nom' => $classe->nom])->values(),
        ];
    }
}
