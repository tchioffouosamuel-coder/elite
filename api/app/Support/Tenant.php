<?php

namespace App\Support;

/**
 * Point d'accès au périmètre école résolu par ScopeEtablissement pour la
 * requête courante. `schoolId()` reste un unique id (jamais null en dehors
 * du mode agrégé du super admin) pour ne pas casser le code existant ;
 * `schoolIds()` porte la liste — un seul id pour un compte normal ou un
 * super admin en mode "focus", tous les ids du complexe en mode agrégé.
 */
class Tenant
{
    public static function schoolId(): ?int
    {
        return app('tenant.school_id');
    }

    /** @return list<int> */
    public static function schoolIds(): array
    {
        return app('tenant.school_ids');
    }

    public static function isAggregate(): bool
    {
        return (bool) app('tenant.is_aggregate');
    }

    /**
     * École à assigner à une donnée créée. En mode agrégé (super admin sans
     * X-School-Id ni école précisée), deviner serait arbitraire : on exige
     * alors un `school_id` explicite plutôt que de choisir à sa place.
     */
    public static function resolveWriteSchoolId(?int $requested): int
    {
        if ($requested !== null) {
            abort_unless(in_array($requested, self::schoolIds(), true), 403, "Cet établissement n'est pas accessible à votre compte.");

            return $requested;
        }

        abort_if(self::isAggregate(), 422, "Veuillez préciser l'établissement.");

        return self::schoolId();
    }
}
