<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Helpers\ApiResponse;
use App\Models\School;
use App\Support\Tenant;
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
    /**
     * @param  string  ...$types  cycles auxquels le module s'adresse
     *
     * En mode focus (une école précise, ou compte non-superadmin), refuse si
     * elle n'est pas du bon type — comportement inchangé. En mode agrégé
     * (super admin, plusieurs écoles de types différents), ne refuse jamais
     * ici : l'école de repli n'a aucune raison d'être du bon type, ça ne veut
     * pas dire que le complexe n'a rien à montrer. La requête se limite
     * elle-même via schoolIdsPour().
     */
    protected function refuserSaufPour(string ...$types): ?JsonResponse
    {
        if (Tenant::isAggregate()) {
            return null;
        }

        $type = School::find(Tenant::schoolId())?->type;

        return in_array($type, $types, true)
            ? null
            : ApiResponse::error($this->messageRefus(), 422);
    }

    /**
     * Écoles accessibles dont le type correspond — à utiliser pour scoper une
     * lecture en mode agrégé, plutôt que Tenant::schoolIds() brut (qui
     * inclurait des écoles où le module n'a pas de sens).
     *
     * @return list<int>
     */
    protected function schoolIdsPour(string ...$types): array
    {
        $accessibles = Tenant::schoolIds();

        if (! Tenant::isAggregate()) {
            return $accessibles;
        }

        return School::whereIn('id', $accessibles)->whereIn('type', $types)->pluck('id')->all();
    }

    /** Redéfinissable pour dire précisément ce qui ne s'applique pas. */
    protected function messageRefus(): string
    {
        return "Ce module ne s'applique pas à cet établissement.";
    }
}
