<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Helpers\ApiResponse;
use App\Models\School;
use Illuminate\Http\JsonResponse;

/**
 * Ferme un module aux établissements qu'il ne concerne pas.
 *
 * Les trois cycles du complexe ne partagent pas les mêmes objets : les degrés
 * d'enseignement n'existent qu'au primaire, les sanctions et les départements
 * qu'au secondaire. Masquer l'entrée de menu ne suffit pas — un appel direct,
 * un lien gardé en favori ou un changement d'école en cours de route
 * contournent l'interface. La règle vit donc aussi côté API.
 */
trait RestreintParTypeEcole
{
    /** @param  string  ...$types  cycles auxquels le module s'adresse */
    protected function refuserSaufPour(string ...$types): ?JsonResponse
    {
        $type = School::find(app('tenant.school_id'))?->type;

        return in_array($type, $types, true)
            ? null
            : ApiResponse::error($this->messageRefus(), 422);
    }

    /** Redéfinissable pour dire précisément ce qui ne s'applique pas. */
    protected function messageRefus(): string
    {
        return "Ce module ne s'applique pas à cet établissement.";
    }
}
