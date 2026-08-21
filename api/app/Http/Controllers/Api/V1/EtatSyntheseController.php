<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Immobilisation;
use App\Models\School;
use App\Support\Pdf\EtatSyntheseGenerator;
use App\Support\Pdf\SerieExercicesGenerator;
use App\Services\Comptabilite\AmortissementService;
use App\Services\Comptabilite\EtatSyntheseService;
use App\Services\Comptabilite\PrelevementsEleveService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * État de synthèse des charges et dépenses, et prélèvements assis sur
 * l'effectif.
 *
 * L'état porte sur un exercice et un établissement : le document que tient
 * l'école ne connaît pas le mode agrégé, chaque entité rend ses comptes
 * séparément. L'école demandée doit donc être explicite et accessible au
 * compte connecté.
 */
class EtatSyntheseController extends Controller
{
    public function __construct(
        private readonly EtatSyntheseService $etats,
        private readonly PrelevementsEleveService $prelevements,
        private readonly AmortissementService $amortissements,
    ) {}

    public function show(Request $request): JsonResponse
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        return ApiResponse::success($this->etats->etablir($schoolId, $anneeId));
    }

    /** La série des exercices d'un établissement, pour lire la tendance. */
    public function serie(Request $request): JsonResponse
    {
        $schoolId = $this->ecole($request);

        return ApiResponse::success(['exercices' => $this->etats->serie($schoolId)]);
    }

    /**
     * La série sur papier. Onze onglets du classeur en une page, avec les deux
     * soldes côte à côte — c'est là que l'écart entre balance et exploitation
     * devient une évidence plutôt qu'une démonstration.
     */
    public function seriePdf(Request $request): Response
    {
        $schoolId = $this->ecole($request);
        $school = School::findOrFail($schoolId);

        $pdf = (new SerieExercicesGenerator)->build($school, $this->etats->serie($schoolId));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="serie-exercices-'.$school->code.'.pdf"',
        ]);
    }

    public function prelevements(Request $request): JsonResponse
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        return ApiResponse::success(['lignes' => $this->prelevements->projeter($schoolId, $anneeId)]);
    }

    public function regulariser(Request $request): JsonResponse
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        $passees = $this->prelevements->regulariser($schoolId, $anneeId, $request->user()?->id);

        return ApiResponse::success(
            ['lignes' => $passees],
            $passees === []
                ? 'Les prélèvements sont déjà à jour.'
                : count($passees).' prélèvement(s) régularisé(s).',
        );
    }

    /**
     * L'état sur papier : c'est sous cette forme qu'il se compare au classeur
     * et qu'il se signe.
     */
    public function pdf(Request $request): Response
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        $etat = $this->etats->etablir($schoolId, $anneeId);

        $pdf = (new EtatSyntheseGenerator)->build(School::findOrFail($schoolId), $etat);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="etat-synthese-'
                .str_replace(['/', ' '], '-', $etat['exercice']['libelle']).'.pdf"',
        ]);
    }

    /** Ce que l'exercice doit doter, bien par bien. */
    public function amortissements(Request $request): JsonResponse
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        return ApiResponse::success(['lignes' => $this->amortissements->projeter($schoolId, $anneeId)]);
    }

    /**
     * Révise un bien : son libellé, et surtout la durée sur laquelle il
     * s'étale. Le compte 624 mêle construction et réfection, qui ne
     * s'amortissent pas au même rythme.
     */
    public function reviserImmobilisation(Request $request, int $id): JsonResponse
    {
        $bien = Immobilisation::forSchool(Tenant::schoolIds())->findOrFail($id);

        $donnees = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:255'],
            'duree_annees' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        try {
            $bien = $this->amortissements->reviser($bien, $donnees);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'immobilisation_id' => $bien->id,
            'libelle' => $bien->libelle,
            'montant' => $bien->montant,
            'duree_annees' => $bien->duree_annees,
            'cumul' => $bien->cumul_amorti,
            'valeur_residuelle' => $bien->valeur_residuelle,
            'dotation' => min($bien->dotationAnnuelle(), $bien->valeur_residuelle),
        ], 'Bien mis à jour.');
    }

    /**
     * Passe les dotations manquantes. C'est ce geste qui ramène au résultat la
     * construction sortie des charges — sans lui, l'investissement disparaît
     * de l'exercice au lieu de s'y étaler.
     */
    public function doter(Request $request): JsonResponse
    {
        [$schoolId, $anneeId] = $this->cadrer($request);

        $passees = $this->amortissements->doter($schoolId, $anneeId);

        return ApiResponse::success(
            ['lignes' => $passees],
            $passees === []
                ? 'Les dotations sont déjà passées pour cet exercice.'
                : count($passees).' dotation(s) enregistrée(s).',
        );
    }

    /**
     * L'exercice et l'établissement du document.
     *
     * @return array{0: int, 1: int}
     */
    private function cadrer(Request $request): array
    {
        $schoolId = $this->ecole($request);

        $donnees = $request->validate([
            'annee_scolaire_id' => [
                'required', 'integer',
                Rule::exists('annee_scolaires', 'id')->where('school_id', $schoolId),
            ],
        ]);

        return [$schoolId, (int) $donnees['annee_scolaire_id']];
    }

    /** Une école, jamais le complexe entier : le document est par entité. */
    private function ecole(Request $request): int
    {
        $accessibles = Tenant::schoolIds();

        $donnees = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($accessibles)],
        ]);

        return (int) $donnees['school_id'];
    }

    /** Exercices disponibles, pour alimenter le sélecteur de l'écran. */
    public function exercices(Request $request): JsonResponse
    {
        $schoolId = $this->ecole($request);

        return ApiResponse::success([
            'exercices' => AnneeScolaire::where('school_id', $schoolId)
                ->orderByDesc('date_debut')
                ->get(['id', 'libelle', 'date_debut', 'date_fin', 'is_active']),
        ]);
    }
}
