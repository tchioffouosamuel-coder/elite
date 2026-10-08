<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Poste desktop déjà en service : les entités `schools`, `settings`,
     * `audit_logs`, `banques`, `banque_mouvements` et
     * `regles_validation_seances` et `calendrier_scolaires` entrent dans le
     * registre de synchronisation.
     * Le curseur partagé par toutes les entités empêcherait de récupérer
     * leurs lignes existantes antérieures à ce curseur.
     *
     * Le prochain cycle repart donc du début ; les écritures hors ligne déjà
     * validées restent rejouables depuis l'outbox locale.
     * Sans effet sur le serveur distant (`SYNC_LOCAL_REPLICA` faux).
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
