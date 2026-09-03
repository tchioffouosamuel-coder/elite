<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\Observation;
use App\Models\User;

/**
 * Fil d'observations d'un élève, partagé entre le tuteur et l'établissement
 * (`Observation.user_id` distingue l'auteur ; {@see \App\Http\Controllers\Api\V1\ParentEspaceController::observations()}
 * lit `origine` depuis son rôle plutôt qu'une colonne dédiée).
 *
 * Seul un message posté par un parent notifie l'établissement — une réponse
 * de l'équipe ne doit pas se notifier elle-même.
 */
class ObservationService extends BaseService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function creer(Eleve $eleve, User $auteur, string $contenu): Observation
    {
        $observation = Observation::create([
            'school_id' => $eleve->school_id,
            'eleve_id' => $eleve->id,
            'user_id' => $auteur->id,
            'contenu' => $contenu,
        ]);

        if ($auteur->hasRole('parent')) {
            $this->notifications->notifierParPermission(
                $eleve->school_id,
                'eleves.manage',
                'observation',
                'Observation transmise par un parent',
                "{$auteur->name} a transmis une observation au sujet de {$eleve->nom_complet}.",
                "/observations?id={$observation->id}",
            );
        }

        return $observation;
    }
}
