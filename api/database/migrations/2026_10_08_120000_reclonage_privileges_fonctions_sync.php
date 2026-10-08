<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Les privilèges de fonction entrent dans les données synchronisées.
     * Reprendre le clonage permet aux postes existants de charger les pivots
     * déjà enregistrés, inchangés depuis leur dernier pull.
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
