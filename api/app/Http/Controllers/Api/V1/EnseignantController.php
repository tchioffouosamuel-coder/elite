<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\Evaluation;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\Sequence;
use App\Services\EvaluationService;
use App\Services\NoteService;
use App\Services\Paie\BaremePaie;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Espace enseignant : ce qu'un compte portant une fiche `Personnel` peut
 * consulter et modifier sur lui-même — sa fiche, sa rémunération — sans
 * privilège de gestion. Même principe que {@see PersonnelEspaceController},
 * pour un périmètre plus large que les seules avances sur salaire.
 */
class EnseignantController extends Controller
{
    /** Champs de gain, dans l'ordre où ils figurent sur le bulletin (cf. RemunerationController). */
    private const GAINS = [
        'salaire_base',
        'prime_anciennete',
        'prime_communication',
        'prime_transport',
        'prime_recherche',
        'prime_performance',
    ];

    public function __construct(
        private readonly EvaluationService $evaluations,
        private readonly NoteService $notes,
    ) {}

    /** Ma fiche personnel : identité, carrière, famille. */
    public function mesInformations(Request $request): JsonResponse
    {
        $personnel = $this->moi($request)->load(['fonctionReference', 'departement', 'school']);

        return ApiResponse::success($personnel);
    }

    /**
     * Mise à jour de ma fiche : seuls les champs sans portée administrative
     * (contact, résidence, situation familiale, diplômes) sont modifiables —
     * matricule, fonction, dates de carrière et statut restent du ressort de
     * l'établissement.
     */
    public function mettreAJourMesInformations(Request $request): JsonResponse
    {
        $personnel = $this->moi($request);

        $donnees = $request->validate([
            'telephone' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
            'situation_matrimoniale' => ['nullable', Rule::in(['celibataire', 'marie', 'divorce', 'veuf'])],
            'nombre_enfants' => ['nullable', 'integer', 'min:0'],
            'diplome_academique' => ['nullable', 'string', 'max:255'],
            'diplome_professionnel' => ['nullable', 'string', 'max:255'],
            'pere_nom_complet' => ['nullable', 'string', 'max:255'],
            'pere_statut' => ['nullable', Rule::in(['vivant', 'decede', ''])],
            'pere_telephone' => ['nullable', 'string', 'max:30'],
            'mere_nom_complet' => ['nullable', 'string', 'max:255'],
            'mere_statut' => ['nullable', Rule::in(['vivant', 'decede', ''])],
            'mere_telephone' => ['nullable', 'string', 'max:30'],
            'enfants' => ['nullable', 'array'],
            'enfants.*.nom_complet' => ['nullable', 'string', 'max:255'],
            'enfants.*.sexe' => ['nullable', Rule::in(['M', 'F', ''])],
            'enfants.*.date_naissance' => ['nullable', 'date'],
        ]);

        $personnel->update($donnees);

        return ApiResponse::success($personnel->fresh(['fonctionReference', 'departement', 'school']), 'Informations mises à jour.');
    }

    /** Ma rémunération en vigueur, lecture seule — aucun privilège finance.paie requis. */
    public function maRemuneration(Request $request): JsonResponse
    {
        $personnel = $this->moi($request);

        $remuneration = Remuneration::where('personnel_id', $personnel->id)
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->first();

        if (! $remuneration) {
            return ApiResponse::success(null);
        }

        $resultat = (new BaremePaie)->calculer($remuneration->only(self::GAINS));

        return ApiResponse::success([
            'date_effet' => $remuneration->date_effet?->format('Y-m-d'),
            'mode' => $remuneration->mode,
            ...$remuneration->only(self::GAINS),
            'brut' => $resultat->brut,
            'charges_salariales' => $resultat->chargesSalariales,
            'net' => $resultat->netAvantDeductions(),
            'cout_employeur' => $resultat->coutEmployeur(),
        ]);
    }

    /**
     * Le département que je dirige : ses matières, les affectations en
     * classe qui en découlent (avec le remplissage des notes de la séquence
     * active), et le taux de remplissage consolidé. 403 si je ne dirige aucun
     * département — l'attribution est la seule porte de ce module.
     */
    public function monDepartement(Request $request): JsonResponse
    {
        $departementIds = $request->user()->perimetre()->departementsDiriges();

        abort_if($departementIds === [], 403, "Vous ne dirigez aucun département.");

        $departement = Departement::forSchool(Tenant::schoolIds())
            ->with(['matieres', 'headPersonnel'])
            ->findOrFail($departementIds[0]);

        $affectations = ClasseMatiere::whereIn('matiere_id', $departement->matieres->pluck('id'))
            ->where('statut', 'actif')
            ->with(['classe', 'matiere', 'enseignant'])
            ->get();

        $sequenceActive = Sequence::whereHas(
            'trimestre',
            fn ($q) => $q->where('is_active', true)->whereHas('anneeScolaire', fn ($aq) => $aq->whereIn('school_id', Tenant::schoolIds()))
        )->first();

        $lignes = $affectations->map(fn (ClasseMatiere $cm) => [
            'classe_matiere_id' => $cm->id,
            'classe' => $cm->classe->nom,
            'matiere' => $cm->matiere->nom,
            'matiere_id' => $cm->matiere_id,
            'enseignant' => $cm->enseignant?->nom_complet,
            'personnel_id' => $cm->personnel_id,
            'taux_remplissage' => $sequenceActive ? $this->notes->tauxRemplissage($cm, $sequenceActive->id) : null,
        ])->values();

        $tauxAvecValeur = $lignes->pluck('taux_remplissage')->filter(fn ($t) => $t !== null);

        return ApiResponse::success([
            'departement' => ['id' => $departement->id, 'nom' => $departement->nom],
            'matieres' => $departement->matieres->map(fn ($m) => ['id' => $m->id, 'nom' => $m->nom])->values(),
            'affectations' => $lignes,
            'taux_remplissage_moyen' => $tauxAvecValeur->isEmpty() ? null : (int) round($tauxAvecValeur->avg()),
        ]);
    }

    /**
     * Ajout d'une évaluation sur une de mes affectations — seul geste de
     * gestion ouvert à l'enseignant sur la fiche de progression, qui reste
     * sinon en lecture seule pour lui (`pedagogie.manage` ne lui est pas
     * accordé). Le périmètre est vérifié ici même, à défaut du middleware
     * `permission`, qui ne peut pas border une route sans `{classeId}`.
     */
    public function ajouterEvaluation(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);

        $perimetre = $request->user()->perimetre();
        abort_unless($perimetre->couvre($classeMatiere->classe_id), 403, "Cette classe n'entre pas dans votre périmètre.");
        if ($perimetre->matieresRestreintesDans($classeMatiere->classe_id)) {
            abort_unless($classeMatiere->personnel_id === $perimetre->personnelId(), 403, "Cette matière ne vous a pas été confiée.");
        }

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Evaluation::TYPES)],
            'date_prevue' => ['nullable', 'date'],
            'bareme' => ['required', 'integer', 'min:1', 'max:100'],
            'competences' => ['nullable', 'string', 'max:1000'],
            'progression_item_id' => [
                'nullable',
                Rule::exists('progression_items', 'id')->where('classe_matiere_id', $classeMatiere->id),
            ],
            'questions' => ['present', 'array'],
            'questions.*.enonce' => ['required', 'string', 'max:1000'],
            'questions.*.bareme_question' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $evaluation = $this->evaluations->creer(
            $classeMatiere,
            [
                'titre' => $data['titre'],
                'type' => $data['type'],
                'date_prevue' => $data['date_prevue'] ?? null,
                'bareme' => $data['bareme'],
                'competences' => $data['competences'] ?? null,
                'progression_item_id' => $data['progression_item_id'] ?? null,
            ],
            $data['questions'],
            $request->user()->personnel?->id,
        );

        return ApiResponse::created($evaluation, 'Évaluation créée.');
    }

    private function moi(Request $request): Personnel
    {
        $personnel = $request->user()->personnel;

        if (! $personnel) {
            throw new NotFoundHttpException('Aucune fiche personnel associée à ce compte.');
        }

        return $personnel;
    }
}
