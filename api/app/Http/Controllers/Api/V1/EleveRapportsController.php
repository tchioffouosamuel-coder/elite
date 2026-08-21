<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\School;
use App\Models\SousSysteme;
use App\Services\EleveService;
use App\Support\Pdf\RecapitulatifEffectifsGenerator;
use App\Support\Pdf\TableauAgesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapports d'effectifs de la page Élèves : récapitulatif façon rentrée
 * scolaire (par école, par sous-système) et pyramide des âges. Séparés
 * d'`EleveController` — ce sont des vues agrégées, pas des opérations sur un
 * élève précis.
 */
class EleveRapportsController extends Controller
{
    public function __construct(private readonly EleveService $service) {}

    public function recapitulatif(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->recapitulatifEffectifs(
            Tenant::schoolIds(),
            $request->integer('classe_id') ?: null,
        ));
    }

    /** `school_id`/`classe_id` restreignent le document à une seule carte : chaque carte de l'écran se télécharge séparément plutôt qu'en un seul PDF pour tout le périmètre. */
    public function recapitulatifPdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $classeId = $request->integer('classe_id') ?: null;
        $tables = collect($this->service->recapitulatifEffectifs($schoolIds, $classeId))
            ->map(fn (array $t) => [
                'titre' => $t['classe'] ? $t['school']['name'].' — '.$t['classe']['nom'] : $t['school']['name'],
                'garcons' => $t['garcons'],
                'filles' => $t['filles'],
                'total' => $t['total'],
            ])
            ->all();

        return $this->pdf($schoolIds, "Récapitulatif d'effectifs", $tables, 'recapitulatif-effectifs.pdf');
    }

    public function recapitulatifSousSystemes(): JsonResponse
    {
        return ApiResponse::success($this->service->recapitulatifEffectifsParSousSysteme(Tenant::schoolIds()));
    }

    /**
     * `school_id` + `sous_systeme_id` restreignent le document à une seule
     * carte (0 = le panier « Sans sous-système »). Sans `sous_systeme_id`,
     * toutes les cartes de l'école demandée sortent dans un seul document.
     */
    public function recapitulatifSousSystemesPdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $sousSystemeVoulu = $request->has('sous_systeme_id') ? $request->integer('sous_systeme_id') : null;
        $tables = [];

        foreach ($this->service->recapitulatifEffectifsParSousSysteme($schoolIds) as $ecole) {
            foreach ($ecole['sous_systemes'] as $ss) {
                $idActuel = $ss['sous_systeme']['id'] ?? 0;

                if ($sousSystemeVoulu !== null && $idActuel !== $sousSystemeVoulu) {
                    continue;
                }

                $tables[] = [
                    'titre' => $ecole['school']['name'].' — '.($ss['sous_systeme']['nom'] ?? 'Sans sous-système'),
                    'garcons' => $ss['garcons'],
                    'filles' => $ss['filles'],
                    'total' => $ss['total'],
                ];
            }
        }

        return $this->pdf($schoolIds, "Récapitulatif d'effectifs par sous-système", $tables, 'recapitulatif-sous-systemes.pdf');
    }

    public function tableauAges(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->tableauAges(
            $this->schoolIds($request),
            $request->integer('sous_systeme_id') ?: null,
            $request->integer('classe_id') ?: null,
        ));
    }

    public function tableauAgesPdf(Request $request): Response
    {
        $schoolIds = $this->schoolIds($request);
        $classeId = $request->integer('classe_id') ?: null;
        $sousSystemeId = $request->integer('sous_systeme_id') ?: null;

        [$school, $perimetre] = match (true) {
            $classeId !== null => (function () use ($classeId, $schoolIds) {
                $classe = Classe::forSchool($schoolIds)->with('school')->findOrFail($classeId);

                return [$classe->school, 'Classe : '.$classe->nom];
            })(),
            $sousSystemeId !== null => (function () use ($sousSystemeId, $schoolIds) {
                $sousSysteme = SousSysteme::forSchool($schoolIds)->with('school')->findOrFail($sousSystemeId);

                return [$sousSysteme->school, 'Sous-système : '.$sousSysteme->nom.' — '.$sousSysteme->school->name];
            })(),
            default => (function () use ($schoolIds) {
                $ecoles = School::whereIn('id', $schoolIds)->get();

                return [$ecoles->first(), $ecoles->count() === 1 ? $ecoles->first()?->name ?? 'Ensemble du périmètre' : 'Ensemble du périmètre'];
            })(),
        };

        $lignes = $this->service->tableauAges($schoolIds, $sousSystemeId, $classeId);
        $pdf = (new TableauAgesGenerator)->build($school, $perimetre, $lignes);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pyramide-ages.pdf"',
        ]);
    }

    /**
     * Périmètre d'écoles pour la requête : celles du tenant, restreintes à
     * `school_id` si fourni (et accessible au compte). Sans lui, mode agrégé.
     *
     * @return list<int>
     */
    private function schoolIds(Request $request): array
    {
        $requested = $request->integer('school_id');

        if (! $requested) {
            return Tenant::schoolIds();
        }

        abort_unless(in_array($requested, Tenant::schoolIds(), true), 403, "Cet établissement n'est pas accessible à votre compte.");

        return [$requested];
    }

    /** @param  list<int>  $schoolIds */
    private function pdf(array $schoolIds, string $titre, array $tables, string $filename): Response
    {
        $school = School::whereIn('id', $schoolIds)->first();
        $pdf = (new RecapitulatifEffectifsGenerator)->build($school, $titre, $tables);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
