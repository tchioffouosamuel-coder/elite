<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\ResolutionAnneeScolaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRapportRentreeTexteRequest;
use App\Services\RapportRentreeTexteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blocs de texte libre du rapport de rentrée MINEDUB : sécurité,
 * gouvernements d'enfants, IRR, événements socio-culturels, fêtes
 * nationales, problèmes/résolutions, doléances, conclusion.
 */
class RapportRentreeTexteController extends Controller
{
    use ResolutionAnneeScolaire;

    private const RUBRIQUES = [
        'securite_cloture', 'securite_detecteur_metaux', 'securite_controle_armes',
        'securite_surveillance_pauses', 'securite_autres_mesures',
        'probleme_infrastructure_maternelle', 'doleances',
        'problemes_fonctionnement', 'resolutions_conseil_maitres',
        'gouvernements_enfants', 'irr', 'evenements_socioculturels',
        'fetes_nationales', 'conclusion_generale',
    ];

    public function __construct(private readonly RapportRentreeTexteService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->resolveAnnee($request, $schoolId);

        return ApiResponse::success($this->service->all($schoolId, $annee));
    }

    public function update(StoreRapportRentreeTexteRequest $request, string $rubrique): JsonResponse
    {
        if (! in_array($rubrique, self::RUBRIQUES, true)) {
            abort(404);
        }

        $data = $request->validated();
        $schoolId = app('tenant.school_id');

        $texte = $this->service->definir($schoolId, $data['annee_scolaire_id'], $rubrique, $data['contenu'] ?? null);

        return ApiResponse::success($texte, 'Enregistré.');
    }
}
