<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restreint une liste aux classes du périmètre de l'utilisateur.
 *
 * Le middleware `permission` couvre les routes qui nomment une classe ; les
 * listes, elles, n'en nomment aucune. Sans ce filtre, un surveillant général
 * chargé de six classes recevrait la liste de toutes les classes de
 * l'établissement, avec tous leurs élèves — et n'aurait de refus qu'en
 * ouvrant la fiche. Autant ne lui montrer que ce qu'il gère.
 *
 * Le modèle qui l'utilise dit par quelle colonne il se rattache à une classe
 * ({@see colonneClasse()}) : `classe_id` pour un élève ou une sanction, `id`
 * pour la classe elle-même.
 */
trait FiltreParPerimetre
{
    /** Colonne portant la classe. À redéfinir sur le modèle Classe (`id`). */
    protected static function colonneClasse(): string
    {
        return 'classe_id';
    }

    /**
     * Sans effet pour un compte qui n'est pas borné (super administrateur,
     * direction, fonctions transverses) : son travail porte sur toute l'école.
     */
    public function scopeDansPerimetre(Builder $query, ?User $user): Builder
    {
        $classes = $user?->perimetre()->classes();

        return $classes === null
            ? $query
            : $query->whereIn($query->getModel()->getTable().'.'.static::colonneClasse(), $classes);
    }
}
