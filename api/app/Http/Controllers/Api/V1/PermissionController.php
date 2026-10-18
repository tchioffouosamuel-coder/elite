<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Support\CataloguePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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

    /**
     * Privilèges d'un agent précis : ceux de sa fonction (hérités, non
     * décochables ici — ça reste le rôle de l'écran « Privilèges » par
     * fonction) et ceux accordés directement à son compte, en plus. Le cumul
     * est volontaire (cf. User::permissionsDeBase()) : une exception
     * individuelle ne doit pas obliger à créer une fonction rien que pour
     * elle.
     */
    public function showUtilisateur(int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(app('tenant.school_id'))->with('user', 'fonctionReference.permissions:id,name')->findOrFail($personnelId);

        abort_unless($personnel->user, 404, "Cet agent n'a pas de compte de connexion.");

        return ApiResponse::success([
            'personnel_id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'modules' => CataloguePermissions::parModule(),
            'permissions_fonction' => $personnel->fonctionReference?->codesPermissions()->values() ?? [],
            'permissions_directes' => $personnel->user->getDirectPermissions()->pluck('name')->values(),
        ]);
    }

    /**
     * Remplace les privilèges accordés EN PROPRE à cet agent — n'importe pas
     * sur ceux hérités de sa fonction, qui restent intacts et se gèrent via
     * `update()` ci-dessus.
     */
    public function updateUtilisateur(Request $request, int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(app('tenant.school_id'))->with('user')->findOrFail($personnelId);

        abort_unless($personnel->user, 404, "Cet agent n'a pas de compte de connexion.");

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $retenus = array_values(array_unique(array_filter($data['permissions'], CataloguePermissions::existe(...))));

        foreach ($retenus as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $personnel->user->syncPermissions($retenus);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponse::success([
            'personnel_id' => $personnel->id,
            'permissions_directes' => $retenus,
        ], "Privilèges individuels de {$personnel->nom_complet} mis à jour.");
    }
}
