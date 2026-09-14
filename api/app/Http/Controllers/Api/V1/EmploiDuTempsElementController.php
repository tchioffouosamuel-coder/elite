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

        [$element, $resultat] = DB::transaction(function () use ($data, $schoolId, $classes) {
            $element = EmploiDuTempsElement::create([
                'school_id' => $schoolId,
                'type' => $data['type'],
                'nom' => $data['nom'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'jours' => $data['jours'],
            ]);
            $element->classes()->sync($classes->pluck('id'));
            $resultat = $this->appliquerElement($element, $classes);

            return [$element->load('classes:id,nom,school_id'), $resultat];
        });

        $message = 'Élément ajouté aux emplois du temps.';
        if ($resultat['ignores'] !== []) {
            $message .= ' '.count($resultat['ignores']).' créneau(x) non généré(s) car en conflit avec un cours existant — voir le détail.';
        }

        return ApiResponse::created([...$this->presenter($element), 'ignores' => $resultat['ignores']], $message);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $element = EmploiDuTempsElement::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $this->valider($request, $element->school_id);
        $classes = Classe::where('school_id', $element->school_id)->whereIn('id', $data['classe_ids'])->get();

        $resultat = DB::transaction(function () use ($element, $data, $classes) {
            $element->update([
                'type' => $data['type'],
                'nom' => $data['nom'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'jours' => $data['jours'],
            ]);
            $element->classes()->sync($classes->pluck('id'));
            EmploiDuTemps::where('emploi_du_temps_element_id', $element->id)->delete();

            return $this->appliquerElement($element->fresh(), $classes);
        });

        $message = 'Élément mis à jour.';
        if ($resultat['ignores'] !== []) {
            $message .= ' '.count($resultat['ignores']).' créneau(x) non généré(s) car en conflit avec un cours existant — voir le détail.';
        }

        return ApiResponse::success([...$this->presenter($element->fresh('classes')), 'ignores' => $resultat['ignores']], $message);
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
        $resultat = $this->appliquerElement($element, $element->classes);

        $message = "{$resultat['creees']} créneau(x) généré(s).";
        if ($resultat['ignores'] !== []) {
            $message .= ' '.count($resultat['ignores']).' ignoré(s) car en conflit avec un créneau existant.';
        }

        return ApiResponse::success($resultat, $message);
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

    private const LIBELLES_JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    /**
     * Matérialise l'élément en créneaux par classe et par jour.
     *
     * Un créneau n'est PAS généré quand il chevaucherait un cours déjà en
     * place ce jour-là pour cette classe — on ne veut pas qu'une pause
     * écrase silencieusement un cours existant. Ce chevauchement est
     * cependant propre à chaque (classe, jour) : rien n'empêche qu'il ne se
     * produise que le mercredi pour une classe, tout en laissant les autres
     * jours parfaitement générés — d'où le retour du détail des créneaux
     * ignorés, sans quoi l'absence d'une pause un jour précis semblait
     * inexplicable alors que l'élément est bien défini pour tous les jours.
     *
     * @return array{creees: int, ignores: list<array{classe: string, jour: int, jour_libelle: string, conflit: string}>}
     */
    private function appliquerElement(EmploiDuTempsElement $element, iterable $classes): array
    {
        $creees = 0;
        $ignores = [];

        foreach ($classes as $classe) {
            foreach ($element->jours as $jour) {
                $existe = EmploiDuTemps::where('emploi_du_temps_element_id', $element->id)
                    ->where('classe_id', $classe->id)->where('jour', $jour)->exists();
                if ($existe) continue;

                $conflit = EmploiDuTemps::where('classe_id', $classe->id)
                    ->where('jour', $jour)
                    ->where('heure_debut', '<', $element->heure_fin)
                    ->where('heure_fin', '>', $element->heure_debut)
                    ->with('classeMatiere.matiere')
                    ->first();

                if ($conflit) {
                    // Un cours ne porte pas de `libelle` (cf. EmploiDuTempsController::valider) :
                    // son nom vient de la matière rattachée. Seuls pause/activité en portent un.
                    $nomConflit = $conflit->libelle ?: $conflit->classeMatiere?->matiere?->nom ?: 'un créneau';

                    $ignores[] = [
                        'classe' => $classe->nom,
                        'jour' => $jour,
                        'jour_libelle' => self::LIBELLES_JOURS[$jour] ?? (string) $jour,
                        'conflit' => sprintf(
                            '%s (%s–%s)',
                            $nomConflit,
                            substr((string) $conflit->heure_debut, 0, 5),
                            substr((string) $conflit->heure_fin, 0, 5),
                        ),
                    ];

                    continue;
                }

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
                $creees++;
            }
        }

        return ['creees' => $creees, 'ignores' => $ignores];
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
