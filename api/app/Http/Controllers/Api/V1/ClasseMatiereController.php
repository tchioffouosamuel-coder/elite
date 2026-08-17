<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClasseMatiereRequest;
use App\Http\Requests\Api\V1\UpdateClasseMatiereRequest;
use App\Http\Resources\Api\V1\ClasseMatiereResource;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseMatiereController extends Controller
{
    public function index(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        $affectations = $classe->classeMatieres()->with(['matiere', 'enseignant'])->orderBy('groupe')->get();

        return ApiResponse::success(ClasseMatiereResource::collection($affectations));
    }

    public function store(StoreClasseMatiereRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        $affectation = $classe->classeMatieres()
            ->create([...$request->validated(), 'groupe' => $request->input('groupe', 1)])
            ->refresh();

        return ApiResponse::created(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Matière affectée à la classe.');
    }

    public function update(UpdateClasseMatiereRequest $request, int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $affectation->update($request->validated());

        return ApiResponse::success(new ClasseMatiereResource($affectation->load(['matiere', 'enseignant'])), 'Affectation mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $affectation = ClasseMatiere::forSchool(app('tenant.school_id'))->findOrFail($id);
        $affectation->delete();

        return ApiResponse::success(message: 'Matière retirée de la classe.');
    }

    /**
     * Classes et matières où l'utilisateur connecté enseigne, toute l'année —
     * contrairement à « Ma journée » (`MaJourneeService::mesAffectations`),
     * qui ne garde que les créneaux prévus le jour même. Un enseignant doit
     * pouvoir consulter le remplissage de n'importe laquelle de ses
     * affectations, pas seulement celle programmée aujourd'hui.
     */
    public function mesAffectations(Request $request): JsonResponse
    {
        $personnelId = $request->user()->personnel?->id;

        if ($personnelId === null) {
            return ApiResponse::success([]);
        }

        $affectations = ClasseMatiere::forSchool(app('tenant.school_id'))
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                // Le titulaire du primaire enseigne toutes les matières de sa
                // classe sans être nommé sur chaque affectation.
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->with(['classe', 'matiere'])
            ->get();

        return ApiResponse::success($affectations->map(fn (ClasseMatiere $cm) => [
            'classe_matiere_id' => $cm->id,
            'classe_id' => $cm->classe->id,
            'classe' => $cm->classe->nom,
            'matiere' => $cm->matiere->nom,
        ])->values());
    }

    /**
     * Duplique une ou plusieurs affectations (matière, enseignant, coefficient,
     * quota horaire) vers une ou plusieurs autres classes — évite de ressaisir
     * à la main la même configuration classe par classe. Une matière déjà
     * affectée dans la classe cible est laissée telle quelle : la copie
     * complète, elle n'écrase jamais un réglage déjà en place.
     */
    public function copier(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');

        $data = $request->validate([
            'affectation_ids' => ['required', 'array', 'min:1'],
            'affectation_ids.*' => ['integer'],
            'classe_ids' => ['required', 'array', 'min:1'],
            'classe_ids.*' => ['integer'],
        ]);

        $affectations = ClasseMatiere::forSchool($schoolId)->whereIn('id', $data['affectation_ids'])->get();
        $classesCibles = Classe::forSchool($schoolId)->whereIn('id', $data['classe_ids'])->get();

        if ($affectations->isEmpty() || $classesCibles->isEmpty()) {
            return ApiResponse::notFound();
        }

        $copiees = 0;
        $ignorees = 0;

        foreach ($classesCibles as $classe) {
            $matieresExistantes = $classe->classeMatieres()->pluck('matiere_id');

            foreach ($affectations as $source) {
                if ($matieresExistantes->contains($source->matiere_id)) {
                    $ignorees++;

                    continue;
                }

                $classe->classeMatieres()->create([
                    'matiere_id' => $source->matiere_id,
                    'personnel_id' => $source->personnel_id,
                    'coefficient' => $source->coefficient,
                    'quota_horaire' => $source->quota_horaire,
                    'groupe' => $source->groupe,
                    'competences' => $source->competences,
                ]);
                $copiees++;
            }
        }

        $message = $ignorees > 0
            ? "{$copiees} affectation(s) copiée(s), {$ignorees} déjà présente(s) ignorée(s)."
            : "{$copiees} affectation(s) copiée(s).";

        return ApiResponse::success(['copiees' => $copiees, 'ignorees' => $ignorees], $message);
    }
}
