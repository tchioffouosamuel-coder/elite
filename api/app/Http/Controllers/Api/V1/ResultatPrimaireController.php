<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Trimestre;
use App\Services\MoyennePrimaireService;
use App\Support\Tenant;
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
        // Classe::forSchool(app('tenant.school_id')) ne couvre qu'UNE école
        // (celle par défaut du compte, ou la première accessible en mode
        // agrégé) : une classe d'une autre école du complexe y était
        // introuvable (404 "No query results for model Classe").
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = $this->trimestre($request, $classe->school_id);

        if (! $trimestre) {
            return ApiResponse::error('Aucun trimestre actif pour cet établissement.', 422);
        }

        // Même enveloppe que le classement du secondaire, pour que les écrans
        // de résultats soient partagés entre les deux moteurs de notation.
        $rows = $this->service->classementGeneral($classe, $trimestre)->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nom_complet,
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
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = $this->trimestre($request, $classe->school_id);

        if (! $trimestre) {
            return ApiResponse::error('Aucun trimestre actif pour cet établissement.', 422);
        }

        $rows = $classe->classeCompetences()->where('statut', 'actif')->with(['competence', 'enseignant'])->get()
            ->map(fn ($cc) => [
                'classe_competence_id' => $cc->id,
                'competence' => $cc->competence->label_fr,
                'competence_en' => $cc->competence->label_en,
                'bareme' => (int) ($cc->competence->notation ?? 20),
                'volets' => $cc->competence->voletsNotes(),
                'enseignant' => $cc->enseignant?->nom_complet ?? $classe->titulaire?->nom_complet,
                'taux' => $this->service->tauxRemplissage($cc, $trimestre),
            ]);

        return ApiResponse::success([
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'competences' => $rows->values(),
        ]);
    }

    /** Décisions de fin d'année : passage ou redoublement selon la moyenne annuelle. */
    public function decisions(int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $anneeScolaireId = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->value('id');

        if (! $anneeScolaireId) {
            return ApiResponse::error("Cet établissement n'a aucune année scolaire active.", 422);
        }

        $rows = $this->service->decisionsAnnuelles($classe, $anneeScolaireId)->map(fn ($row) => [
            'eleve_id' => $row['eleve']->id,
            'nom_complet' => $row['eleve']->nom_complet,
            'matricule' => $row['eleve']->matricule,
            'moyenne_annuelle' => $row['moyenne_annuelle'],
            'decision' => $row['decision'],
        ]);

        return ApiResponse::success($rows->values());
    }

    /** @param int|null $schoolId École de la classe consultée, à défaut celle par défaut du compte. */
    private function trimestre(Request $request, ?int $schoolId = null): ?Trimestre
    {
        $schoolId ??= app('tenant.school_id');
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $schoolId)
        );

        return $request->integer('trimestre_id')
            ? (clone $query)->find($request->integer('trimestre_id'))
            : (clone $query)->where('is_active', true)->first();
    }
}
