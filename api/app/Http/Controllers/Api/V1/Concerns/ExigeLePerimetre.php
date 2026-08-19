<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Refuse une action dont la classe visée sort du périmètre du compte.
 *
 * Le middleware `permission` s'en charge dès que la classe figure dans l'URL.
 * Restent les actions qui la déduisent de leur charge utile — enregistrer une
 * sanction contre un élève, corriger une sanction existante : la classe n'y
 * apparaît qu'après lecture en base, donc après le middleware. Ce trait leur
 * donne la même vérification, au moment où la classe devient connue.
 */
trait ExigeLePerimetre
{
    protected function refuserHorsPerimetre(Request $request, ?int $classeId, string $permission): ?JsonResponse
    {
        if ($classeId === null || $request->user()?->peutSurClasse($permission, $classeId)) {
            return null;
        }

        return ApiResponse::forbidden(
            "Cette classe n'entre pas dans votre périmètre : vous n'y enseignez pas et elle ne vous a pas été confiée.",
            [$permission],
        );
    }
}
