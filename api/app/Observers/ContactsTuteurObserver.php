<?php

namespace App\Observers;

use App\Models\Eleve;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;

/**
 * Branché à la fois sur `Tuteur` et sur `TuteurTelephone` (cf.
 * AppServiceProvider) : chaque événement reçoit donc l'un ou l'autre modèle,
 * d'où les signatures en union — typer un seul des deux faisait lever une
 * TypeError à chaque sauvegarde de l'autre.
 */
class ContactsTuteurObserver
{
    /**
     * Levé par `sync:pull` le temps d'appliquer les lignes reçues du serveur
     * distant : elles portent déjà leur propre `updated_at`, il n'y a
     * personne à prévenir d'un changement qui vient justement d'arriver.
     */
    public static bool $suspendu = false;

    public function updated(Tuteur|TuteurTelephone $modele): void
    {
        // Côté téléphone, `saved()` couvre déjà la mise à jour.
        if (! self::$suspendu && $modele instanceof Tuteur) {
            $this->marquerElevesModifies($modele->eleves()->pluck('eleves.id'));
        }
    }

    public function saved(Tuteur|TuteurTelephone $modele): void
    {
        if (! self::$suspendu && $modele instanceof TuteurTelephone) {
            $this->marquerTuteurModifie($modele->tuteur_id);
        }
    }

    public function deleted(Tuteur|TuteurTelephone $modele): void
    {
        if (! self::$suspendu && $modele instanceof TuteurTelephone) {
            $this->marquerTuteurModifie($modele->tuteur_id);
        }
    }

    private function marquerTuteurModifie(int $tuteurId): void
    {
        $eleveIds = Tuteur::query()->find($tuteurId)?->eleves()->pluck('eleves.id') ?? collect();
        $this->marquerElevesModifies($eleveIds);
    }

    private function marquerElevesModifies(iterable $eleveIds): void
    {
        Eleve::query()->whereIn('id', $eleveIds)->update(['updated_at' => now()]);
    }
}
