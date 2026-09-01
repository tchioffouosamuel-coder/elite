<?php

namespace App\Support;

use App\Models\Eleve;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Périmètre d'un compte parent : ses propres enfants, et rien d'autre.
 *
 * Le rôle `parent` porte les mêmes privilèges (`eleves.view`, `finance.view`…)
 * que le personnel, mais `Tenant::schoolIds()` ne borne qu'à l'école — un
 * parent y verrait sinon tous les élèves de la classe de son enfant. Chaque
 * point d'entrée du portail parent doit donc vérifier explicitement
 * l'appartenance via cette classe plutôt que de se fier au seul périmètre
 * école, qui reste nécessaire mais pas suffisant.
 */
class ParentAccess
{
    /**
     * Fiches tuteur du compte connecté. Normalement une seule — un compte
     * parent est créé pour une fiche tuteur précise — mais on ne présume pas
     * de l'unicité pour rester robuste à une fusion future.
     *
     * @return Collection<int, Tuteur>
     */
    public static function tuteurs(User $user): Collection
    {
        return Tuteur::where('user_id', $user->id)->get();
    }

    /**
     * Enfants du compte connecté, toutes fiches tuteur confondues.
     *
     * @return Collection<int, Eleve>
     */
    public static function enfants(User $user): Collection
    {
        $tuteurIds = self::tuteurs($user)->pluck('id');

        return Eleve::whereHas(
            'tuteurs',
            fn ($q) => $q->whereIn('tuteurs.id', $tuteurIds)
        )->with('classe', 'school')->orderBy('nom_complet')->get();
    }

    /** @return list<int> */
    public static function enfantIds(User $user): array
    {
        return self::enfants($user)->pluck('id')->all();
    }

    /**
     * L'élève demandé, si et seulement s'il est un enfant du compte connecté.
     * Un 404 plutôt qu'un 403 : la fiche existe peut-être, mais rien ne doit
     * confirmer son existence à qui n'y a pas droit.
     */
    public static function assertEnfant(User $user, int $eleveId): Eleve
    {
        $eleve = Eleve::with('classe.sousSysteme', 'school', 'tuteurs.telephones')
            ->whereHas('tuteurs', fn ($q) => $q->whereIn('tuteurs.id', self::tuteurs($user)->pluck('id')))
            ->where('eleves.id', $eleveId)
            ->first();

        if (! $eleve) {
            throw new NotFoundHttpException('Élève introuvable.');
        }

        return $eleve;
    }
}
