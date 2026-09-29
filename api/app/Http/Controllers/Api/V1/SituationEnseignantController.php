<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusAffectation;
use App\Models\Eleve;
use App\Services\ScolariteService;
use App\Helpers\ApiResponse;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce qu'un enseignant voit des finances de ses élèves : qui est insolvable,
 * qui prend le bus — des noms, jamais de montants. La fiche financière
 * détaillée (dû, versé, reste, tarif du bus) reste à la caisse ; ces deux
 * listes répondent seulement à « qui relancer » et « qui part en bus », et
 * sont bornées aux classes du compte (`dansPerimetre`).
 */
class SituationEnseignantController extends Controller
{
    public function __construct(private readonly ScolariteService $scolarite) {}

    public function insolvables(Request $request): JsonResponse
    {
        $perimetre = Eleve::forSchool(Tenant::schoolIds())
            ->dansPerimetre($request->user())
            ->pluck('id')
            ->flip();

        $lignes = collect($this->scolarite->insolvables(Tenant::schoolIds())['lignes'] ?? [])
            ->filter(fn (array $ligne) => $perimetre->has($ligne['eleve']['id']))
            ->map(fn (array $ligne) => [
                'id' => $ligne['eleve']['id'],
                'matricule' => $ligne['eleve']['matricule'],
                'nom_complet' => $ligne['eleve']['nom_complet'],
                'classe' => $ligne['eleve']['classe'],
            ])
            ->sortBy(['classe', 'nom_complet'])
            ->values();

        return ApiResponse::success($lignes);
    }

    public function bus(Request $request): JsonResponse
    {
        $user = $request->user();

        $lignes = BusAffectation::actives()
            ->whereHas('anneeScolaire', fn ($q) => $q->where('is_active', true))
            ->whereHas('eleve', fn ($q) => $q->forSchool(Tenant::schoolIds())->dansPerimetre($user))
            ->with(['eleve.classe', 'trajet', 'arret'])
            ->get()
            ->map(fn (BusAffectation $a) => [
                'id' => $a->eleve->id,
                'matricule' => $a->eleve->matricule,
                'nom_complet' => $a->eleve->nom_complet,
                'classe' => $a->eleve->classe?->nom,
                'trajet' => $a->trajet?->nom,
                'arret' => $a->arret?->nom,
            ])
            ->sortBy(['classe', 'nom_complet'])
            ->values();

        return ApiResponse::success($lignes);
    }
}
