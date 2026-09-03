<?php

namespace App\Support;

use App\Models\Eleve;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Périmètre d'un compte élève : sa propre fiche, et rien d'autre — pendant
 * de {@see ParentAccess} pour le portail élève, plus simple puisqu'un compte
 * élève ne porte jamais qu'une seule fiche (contrairement à un tuteur, qui
 * peut avoir plusieurs enfants).
 *
 * Le rôle `eleve` porte les mêmes privilèges (`notes.view`, `discipline.view`…)
 * que le personnel, mais `Tenant::schoolIds()` ne borne qu'à l'école — un
 * élève y verrait sinon toute sa classe. Chaque point d'entrée du portail
 * élève doit donc appeler {@see assertMoi()} plutôt que de se fier au seul
 * périmètre école.
 */
class EleveAccess
{
    /**
     * La fiche du compte connecté. 404 plutôt que 403 : cohérent avec
     * {@see ParentAccess::assertEnfant()} — rien ne doit confirmer l'existence
     * d'une fiche à qui n'y a pas droit, et un compte `eleve` sans fiche liée
     * (cas impossible en usage normal, l'accès n'existe que via
     * {@see \App\Services\CompteEleveService::assurer()}) ne doit pas planter
     * autrement qu'un élève qui n'existerait pas.
     */
    public static function assertMoi(User $user): Eleve
    {
        $eleve = Eleve::with('classe.sousSysteme', 'school', 'tuteurs.telephones')
            ->where('user_id', $user->id)
            ->first();

        if (! $eleve) {
            throw new NotFoundHttpException('Fiche élève introuvable pour ce compte.');
        }

        return $eleve;
    }
}
