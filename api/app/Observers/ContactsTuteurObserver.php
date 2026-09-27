<?php

namespace App\Observers;

use App\Models\Eleve;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;

class ContactsTuteurObserver
{
    public function updated(Tuteur $tuteur): void
    {
        $this->marquerElevesModifies($tuteur->eleves()->pluck('eleves.id'));
    }

    public function saved(TuteurTelephone $telephone): void
    {
        $this->marquerTuteurModifie($telephone->tuteur_id);
    }

    public function deleted(TuteurTelephone $telephone): void
    {
        $this->marquerTuteurModifie($telephone->tuteur_id);
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
