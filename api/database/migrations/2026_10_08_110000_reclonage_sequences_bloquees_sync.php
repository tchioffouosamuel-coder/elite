<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Les séquences étaient déjà synchronisées avant l'ajout de
     * `saisie_ouverte` au registre. Réinitialiser le curseur partagé permet
     * aux postes existants de récupérer l'état de blocage des séquences
     * inchangées côté serveur.
     */
    public function up(): void
    {
        if (! config('sync.local_replica')) {
            return;
        }

        DesktopProvisioningEcole::query()->update(['curseur_sync' => null]);
    }

    public function down(): void
    {
        //
    }
};
