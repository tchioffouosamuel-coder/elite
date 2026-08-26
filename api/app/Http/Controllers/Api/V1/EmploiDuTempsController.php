<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Trimestre;
use App\Services\EmploiDuTempsService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmploiDuTempsController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    public function index(int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        return ApiResponse::success($this->service->grille($classe)->map(EmploiDuTempsService::presenter(...)));
    }

    public function store(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);
        $data = $this->valider($request, $classe);

        $associees = $data['classes_associees'];
        unset($data['classes_associees']);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'], null, $associees)) {
            return ApiResponse::error("Ce créneau en chevauche un autre pour l'une des classes concernées.", 422);
        }

        $classeMatiere = ClasseMatiere::findOrFail($data['classe_matiere_id']);

        if ($erreurQuota = $this->service->depasseQuota($classeMatiere, $data['heure_debut'], $data['heure_fin'])) {
            return ApiResponse::error($erreurQuota, 422);
        }

        $creneau = EmploiDuTemps::create([...$data, 'classe_id' => $classe->id, 'school_id' => $classe->school_id]);
        $creneau->classesAssociees()->sync($associees);

        return ApiResponse::created(
            EmploiDuTempsService::presenter($creneau->load('classeMatiere.matiere', 'classesAssociees')),
            'Créneau ajouté.',
        );
    }

    public function update(Request $request, int $classeId, int $id): JsonResponse
    {
        $classe = $this->classe($classeId);
        $creneau = EmploiDuTemps::where('classe_id', $classe->id)->findOrFail($id);
        $data = $this->valider($request, $classe);

        $associees = $data['classes_associees'];
        unset($data['classes_associees']);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'], $creneau->id, $associees)) {
            return ApiResponse::error("Ce créneau en chevauche un autre pour l'une des classes concernées.", 422);
        }

        $classeMatiere = ClasseMatiere::findOrFail($data['classe_matiere_id']);

        if ($erreurQuota = $this->service->depasseQuota($classeMatiere, $data['heure_debut'], $data['heure_fin'], $creneau->id)) {
            return ApiResponse::error($erreurQuota, 422);
        }

        $creneau->update($data);
        $creneau->classesAssociees()->sync($associees);

        return ApiResponse::success(
            EmploiDuTempsService::presenter($creneau->fresh(['classeMatiere.matiere', 'classesAssociees'])),
            'Créneau mis à jour.',
        );
    }

    public function destroy(int $classeId, int $id): JsonResponse
    {
        $classe = $this->classe($classeId);
        EmploiDuTemps::where('classe_id', $classe->id)->findOrFail($id)->delete();

        return ApiResponse::success(message: 'Créneau supprimé.');
    }

    /** Matérialise les créneaux en séances datées sur une période. */
    public function genererSeances(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'trimestre_id' => ['nullable', 'integer'],
        ]);

        $trimestre = $data['trimestre_id'] ?? null
            ? Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $classe->school_id))
                ->find($data['trimestre_id'])
            : null;

        $creees = $this->service->genererSeances(
            $classe,
            Carbon::parse($data['date_debut']),
            Carbon::parse($data['date_fin']),
            $trimestre,
        );

        return ApiResponse::success(['creees' => $creees], "{$creees} séance(s) générée(s).");
    }

    private function valider(Request $request, Classe $classe): array
    {
        $data = $request->validate([
            'classe_matiere_id' => ['required', 'integer'],
            'jour' => ['required', 'integer', 'min:1', 'max:7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:50'],
            // Tronc commun : les classes qui rejoignent celle-ci sur ce créneau.
            'classes_associees' => ['sometimes', 'array'],
            'classes_associees.*' => ['integer', 'distinct'],
        ]);

        $associees = collect($data['classes_associees'] ?? [])
            ->map(fn ($id) => (int) $id)
            // La classe porteuse n'a pas à figurer parmi celles qui la
            // rejoignent : elle y serait comptée deux fois à l'appel.
            ->reject(fn (int $id) => $id === $classe->id)
            ->unique()
            ->values();

        if ($associees->isNotEmpty()) {
            $valides = Classe::where('school_id', $classe->school_id)
                ->whereIn('id', $associees)
                ->pluck('id');

            abort_unless(
                $valides->count() === $associees->count(),
                422,
                "Une classe associée n'appartient pas à cet établissement.",
            );
        }

        abort_unless(
            ClasseMatiere::where('classe_id', $classe->id)->whereKey($data['classe_matiere_id'])->exists(),
            422,
            "Cette matière n'est pas affectée à la classe."
        );

        $data['classes_associees'] = $associees->all();

        return $data;
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(Tenant::schoolIds())->findOrFail($id);
    }
}
