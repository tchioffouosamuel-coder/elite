<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\TroncCommunGroupe;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TroncCommunGroupeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $schoolId = (int) app('tenant.school_id');
        $data = $request->validate([
            'matiere_id' => ['required', 'integer'],
            'personnel_id' => ['required', 'integer'],
            'nom' => ['required', 'string', 'max:150'],
            'classe_ids' => ['required', 'array', 'min:2'],
            'classe_ids.*' => ['integer', 'distinct'],
        ]);

        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($data['matiere_id']);
        $personnel = Personnel::findOrFail($data['personnel_id']);
        $classes = Classe::where('school_id', $schoolId)->whereIn('id', $data['classe_ids'])->get();

        abort_unless($classes->count() === count($data['classe_ids']), 422, "Une classe n'appartient pas à cet établissement.");
        abort_unless($personnel->school_id === $schoolId, 422, "L'enseignant n'appartient pas à cet établissement.");

        $suffixes = $classes->map(fn(Classe $classe) => $this->suffixeNiveau($classe->nom))->unique()->values();
        abort_unless($suffixes->count() === 1 && $suffixes->first() !== '', 422, 'Les classes doivent appartenir au même niveau pour un tronc commun.');

        $eligibles = ClasseMatiere::whereIn('classe_id', $classes->pluck('id'))
            ->where('matiere_id', $matiere->id)
            ->where('personnel_id', $personnel->id)
            ->count();
        abort_unless($eligibles === $classes->count(), 422, 'Toutes les classes doivent avoir cette matière avec le même enseignant.');

        $groupe = DB::transaction(function () use ($data, $schoolId, $classes, $matiere, $personnel) {
            $groupe = TroncCommunGroupe::create([
                'school_id' => $schoolId,
                'matiere_id' => $matiere->id,
                'personnel_id' => $personnel->id,
                'nom' => $data['nom'],
            ]);
            $groupe->classes()->sync($classes->pluck('id'));

            return $groupe->load(['matiere', 'personnel', 'classes']);
        });

        return ApiResponse::created($this->presenter($groupe), 'Tronc commun enregistré.');
    }

    public function index(): JsonResponse
    {
        $groupes = TroncCommunGroupe::forSchool(Tenant::schoolIds())
            ->with(['matiere', 'personnel', 'classes'])
            ->latest()->get();

        return ApiResponse::success($groupes->map(fn(TroncCommunGroupe $groupe) => $this->presenter($groupe)));
    }

    public function destroy(int $id): JsonResponse
    {
        TroncCommunGroupe::forSchool(Tenant::schoolIds())->findOrFail($id)->delete();

        return ApiResponse::success(message: 'Tronc commun supprimé.');
    }

    private function presenter(TroncCommunGroupe $groupe): array
    {
        return [
            'id' => $groupe->id,
            'nom' => $groupe->nom,
            'matiere_id' => $groupe->matiere_id,
            'matiere' => $groupe->matiere?->nom,
            'personnel_id' => $groupe->personnel_id,
            'enseignant' => $groupe->personnel?->nom_complet,
            'classes' => $groupe->classes->map(fn(Classe $classe) => ['id' => $classe->id, 'nom' => $classe->nom])->values(),
        ];
    }

    private function suffixeNiveau(string $nom): string
    {
        $morceaux = preg_split('/\s+/', trim($nom));

        return mb_strtoupper((string) end($morceaux));
    }
}
