<?php

namespace App\Services;

use App\Models\Observation;
use App\Models\Personnel;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\Telephone;

/**
 * Détecte et fusionne les doublons personnel/parent : un agent qui est aussi
 * tuteur d'un élève se retrouvait avec deux comptes distincts (l'un pour sa
 * fiche Personnel, l'autre pour sa fiche Tuteur), faute pour
 * {@see CompteAgentService} et {@see CompteParentService} de vérifier qu'un
 * compte existait déjà pour son numéro avant d'en créer un second — corrigés
 * pour ne plus reproduire ce doublon à l'avenir. Ce service nettoie les
 * doublons déjà créés, à la demande (écran Personnel) ou en ligne de commande
 * (cf. `comptes:fusionner-personnel-parent`).
 *
 * Les observations laissées par l'ancien compte parent sont réattribuées au
 * compte gardé avant suppression : `observations.user_id` est en
 * `cascadeOnDelete`, et c'est la seule table où un compte parent a pu écrire
 * un contenu qu'on ne veut pas perdre. Le reste des références à l'ancien
 * compte (device_tokens, notifications, jetons d'API…) disparaît en cascade
 * sans conséquence.
 */
class FusionComptesPersonnelParentService extends BaseService
{
    /**
     * Paires personnel/tuteur d'une même personne (même téléphone, même
     * école) mais deux comptes différents — à fusionner.
     *
     * @return list<array{personnel: Personnel, tuteur: Tuteur}>
     */
    public function detecter(int|array $schoolId): array
    {
        $personnels = Personnel::forSchool($schoolId)->whereNotNull('user_id')->whereNotNull('telephone')->where('telephone', '!=', '')->get();
        $tuteurs = Tuteur::forSchool($schoolId)->whereNotNull('user_id')->whereNotNull('telephone')->where('telephone', '!=', '')->get();

        $tuteursParCle = $tuteurs->groupBy(fn (Tuteur $t) => $t->school_id.'|'.Telephone::normaliser($t->telephone));

        $paires = [];

        foreach ($personnels as $personnel) {
            $cle = $personnel->school_id.'|'.Telephone::normaliser($personnel->telephone);

            foreach ($tuteursParCle->get($cle, collect()) as $tuteur) {
                if ($tuteur->user_id === $personnel->user_id) {
                    continue;
                }

                $paires[] = ['personnel' => $personnel, 'tuteur' => $tuteur];
            }
        }

        return $paires;
    }

    /**
     * Résumé lisible des paires détectées, pour l'aperçu affiché avant
     * confirmation (écran Personnel comme commande Artisan).
     *
     * @return list<array{personnel: string, tuteur: string, personnel_id: int, tuteur_id: int}>
     */
    public function apercu(int|array $schoolId): array
    {
        return array_map(
            fn (array $paire) => [
                'personnel' => $paire['personnel']->nom_complet,
                'personnel_id' => $paire['personnel']->id,
                'tuteur' => $paire['tuteur']->nom_complet,
                'tuteur_id' => $paire['tuteur']->id,
            ],
            $this->detecter($schoolId),
        );
    }

    /**
     * Fusionne toutes les paires détectées — rattache chaque fiche tuteur au
     * compte du personnel correspondant, et supprime le compte parent devenu
     * superflu.
     *
     * @return array{fusionnes: int, paires: list<array{personnel: string, tuteur: string}>}
     */
    public function fusionner(int|array $schoolId): array
    {
        $paires = $this->detecter($schoolId);
        $resume = [];

        foreach ($paires as ['personnel' => $personnel, 'tuteur' => $tuteur]) {
            $this->transaction(function () use ($personnel, $tuteur) {
                $ancienUserId = $tuteur->user_id;
                $nouveauUserId = $personnel->user_id;

                Observation::where('user_id', $ancienUserId)->update(['user_id' => $nouveauUserId]);

                $tuteur->forceFill(['user_id' => $nouveauUserId])->save();

                $nouveauUser = $personnel->user;

                if (! $nouveauUser->hasRole('parent')) {
                    $nouveauUser->assignRole('parent');
                }

                User::find($ancienUserId)?->delete();
            });

            $resume[] = ['personnel' => $personnel->nom_complet, 'tuteur' => $tuteur->nom_complet];
        }

        return ['fusionnes' => count($resume), 'paires' => $resume];
    }
}
