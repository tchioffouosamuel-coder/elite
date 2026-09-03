<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\JustificationAbsence;
use App\Models\Presence;
use App\Models\Tuteur;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Justifications d'absence déposées par les parents, par anticipation.
 *
 * @see \App\Services\EmploiDuTempsService::enregistrerAppel() pour le
 *      rapprochement automatique avec le pointage réel.
 */
class JustificationAbsenceService extends BaseService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  array{date_debut: string, date_fin?: ?string, motif: string, description?: ?string}  $donnees
     */
    public function soumettre(Tuteur $tuteur, Eleve $eleve, array $donnees): JustificationAbsence
    {
        if (! $tuteur->eleves()->where('eleves.id', $eleve->id)->exists()) {
            throw new RuntimeException("Cet élève n'est pas rattaché à votre compte.");
        }

        $justification = JustificationAbsence::create([
            'school_id' => $eleve->school_id,
            'eleve_id' => $eleve->id,
            'tuteur_id' => $tuteur->id,
            'date_debut' => $donnees['date_debut'],
            'date_fin' => $donnees['date_fin'] ?? $donnees['date_debut'],
            'motif' => $donnees['motif'],
            'description' => $donnees['description'] ?? null,
        ]);

        $this->notifications->notifierParPermission(
            $eleve->school_id,
            'eleves.manage',
            'justification_absence',
            "Justification d'absence déposée",
            "{$tuteur->nom_complet} justifie une absence de {$eleve->nom_complet}.",
            "/justifications?id={$justification->id}",
        );

        return $justification;
    }

    /** @return Collection<int, JustificationAbsence> */
    public function pourEnfant(int $eleveId): Collection
    {
        return JustificationAbsence::where('eleve_id', $eleveId)->latest('date_debut')->get();
    }

    /**
     * Justification en attente couvrant cette date, si elle existe — c'est
     * elle que l'appel doit appliquer par défaut plutôt que de laisser
     * l'absence sans motif.
     */
    public function trouverPour(int $eleveId, string $date): ?JustificationAbsence
    {
        return JustificationAbsence::enAttentePour($eleveId, $date)->first();
    }

    public function marquerAppliquee(JustificationAbsence $justification, Presence $presence): void
    {
        $justification->update(['statut' => 'appliquee', 'presence_id' => $presence->id]);
    }
}
