<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Trimestre;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmploiDuTempsController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    public function index(int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        return ApiResponse::success($this->service->grille($classe)->map($this->presenter(...)));
    }

    public function store(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);
        $data = $this->valider($request, $classe);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'])) {
            return ApiResponse::error('Ce créneau en chevauche un autre pour cette classe.', 422);
        }

        $creneau = EmploiDuTemps::create([...$data, 'classe_id' => $classe->id, 'school_id' => $classe->school_id]);

        return ApiResponse::created($this->presenter($creneau->load('classeMatiere.matiere')), 'Créneau ajouté.');
    }

    public function update(Request $request, int $classeId, int $id): JsonResponse
    {
        $classe = $this->classe($classeId);
        $creneau = EmploiDuTemps::where('classe_id', $classe->id)->findOrFail($id);
        $data = $this->valider($request, $classe);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'], $creneau->id)) {
            return ApiResponse::error('Ce créneau en chevauche un autre pour cette classe.', 422);
        }

        $creneau->update($data);

        return ApiResponse::success($this->presenter($creneau->load('classeMatiere.matiere')), 'Créneau mis à jour.');
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
        ]);

        abort_unless(
            ClasseMatiere::where('classe_id', $classe->id)->whereKey($data['classe_matiere_id'])->exists(),
            422,
            "Cette matière n'est pas affectée à la classe."
        );

        return $data;
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(app('tenant.school_id'))->findOrFail($id);
    }

    private function presenter(EmploiDuTemps $creneau): array
    {
        return [
            'id' => $creneau->id,
            'jour' => $creneau->jour,
            'heure_debut' => substr((string) $creneau->heure_debut, 0, 5),
            'heure_fin' => substr((string) $creneau->heure_fin, 0, 5),
            'salle' => $creneau->salle,
            'classe_matiere_id' => $creneau->classe_matiere_id,
            'matiere' => $creneau->classeMatiere?->matiere?->nom,
            'enseignant' => $creneau->classeMatiere?->enseignant?->nom_complet,
        ];
    }
}
