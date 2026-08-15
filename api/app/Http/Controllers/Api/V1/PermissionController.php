<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FonctionReferentiel;
use App\Support\CataloguePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administration des privilèges, réservée au super administrateur.
 *
 * Il n'y a pas de création de privilège : la liste vient du catalogue
 * (cf. CataloguePermissions), qui suit les routes. Ce qui se pilote ici, c'est
 * la composition des groupes de privilèges attachés à chaque fonction.
 */
class PermissionController extends Controller
{
    /**
     * Matrice complète pour l'écran de gestion : le catalogue par module, et
     * pour chaque fonction de l'établissement les privilèges qu'elle accorde.
     */
    public function index(): JsonResponse
    {
        $fonctions = FonctionReferentiel::forSchool(app('tenant.school_id'))
            ->with('permissions:id,name')
            ->withCount('personnels')
            ->orderBy('label_fr')
            ->get();

        return ApiResponse::success([
            'modules' => CataloguePermissions::parModule(),
            'fonctions' => $fonctions->map(fn (FonctionReferentiel $fonction) => [
                'id' => $fonction->id,
                'label_fr' => $fonction->label_fr,
                'label_en' => $fonction->label_en,
                'label' => $fonction->label(),
                'personnels_count' => $fonction->personnels_count,
                'permissions' => $fonction->codesPermissions()->values(),
            ])->values(),
        ]);
    }

    public function show(int $fonctionId): JsonResponse
    {
        $fonction = FonctionReferentiel::forSchool(app('tenant.school_id'))
            ->with('permissions:id,name')
            ->findOrFail($fonctionId);

        return ApiResponse::success([
            'id' => $fonction->id,
            'label' => $fonction->label(),
            'permissions' => $fonction->codesPermissions()->values(),
        ]);
    }

    /**
     * Remplace le groupe de privilèges d'une fonction. Tous les agents qui la
     * portent voient leurs droits changer au prochain appel — d'où le décompte
     * renvoyé, pour que l'écran puisse dire combien de comptes sont concernés.
     */
    public function update(Request $request, int $fonctionId): JsonResponse
    {
        $fonction = FonctionReferentiel::forSchool(app('tenant.school_id'))
            ->withCount('personnels')
            ->findOrFail($fonctionId);

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $retenus = $fonction->synchroniserPermissions($data['permissions']);

        return ApiResponse::success([
            'id' => $fonction->id,
            'permissions' => $retenus,
            'personnels_count' => $fonction->personnels_count,
        ], "Privilèges de « {$fonction->label()} » mis à jour ({$fonction->personnels_count} agent(s) concerné(s)).");
    }
}
