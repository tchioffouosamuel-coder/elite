<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Trimestre;
use App\Services\MoyennePrimaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Résultats du primaire et de la maternelle : classement, état de remplissage
 * des notes et décisions de passage en classe supérieure.
 */
class ResultatPrimaireController extends Controller
{
    public function __construct(private readonly MoyennePrimaireService $service) {}

    public function classement(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = $this->trimestre($request);

        if (! $trimestre) {
            return ApiResponse::error('Aucun trimestre actif pour cet établissement.', 422);
        }

        // Même enveloppe que le classement du secondaire, pour que les écrans
        // de résultats soient partagés entre les deux moteurs de notation.
        $rows = $this->service->classementGeneral($classe, $trimestre)->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nomComplet(),
            'matricule' => $row['eleve']->matricule,
            'sexe' => $row['eleve']->sexe,
            'moyenne' => $row['moyenne'],
            'rang' => $row['rang'],
            'cote' => $this->service->appreciationCompetence($row['moyenne'], 20),
            'mention' => null,
        ]);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'eleves' => $rows->values(),
        ]);
    }

    public function remplissage(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);
        $trimestre = $this->trimestre($request);

        if (! $trimestre) {
            return ApiResponse::error('Aucun trimestre actif pour cet établissement.', 422);
        }

        $rows = $classe->classeMatieres()->where('statut', 'actif')->with(['matiere', 'enseignant'])->get()
            ->map(fn ($cm) => [
                'classe_matiere_id' => $cm->id,
                'matiere' => $cm->matiere->nom,
                'bareme' => (int) ($cm->matiere->notation ?? 20),
                'volets' => $cm->matiere->composantes(),
                'enseignant' => $cm->enseignant?->nomComplet() ?? $classe->titulaire?->nomComplet(),
                'taux' => $this->service->tauxRemplissage($cm, $trimestre),
            ]);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'matieres' => $rows->values(),
        ]);
    }

    /** Décisions de fin d'année : passage ou redoublement selon la moyenne annuelle. */
    public function decisions(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        if (! $classe->annee_scolaire_id) {
            return ApiResponse::error("Cette classe n'est rattachée à aucune année scolaire.", 422);
        }

        $rows = $this->service->decisionsAnnuelles($classe, $classe->annee_scolaire_id)->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nomComplet(),
            'matricule' => $row['eleve']->matricule,
            'moyenne_annuelle' => $row['moyenne_annuelle'],
            'decision' => $row['decision'],
        ]);

        return ApiResponse::success($rows->values());
    }

    private function trimestre(Request $request): ?Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        );

        return $request->integer('trimestre_id')
            ? (clone $query)->find($request->integer('trimestre_id'))
            : (clone $query)->where('is_active', true)->first();
    }
}
