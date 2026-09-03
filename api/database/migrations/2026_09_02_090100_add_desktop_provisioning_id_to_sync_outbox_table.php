<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un poste desktop peut désormais lier plusieurs comptes
     * ({@see \App\Http\Controllers\Api\V1\DesktopProvisioningController}) :
     * chaque écriture en attente doit savoir de quel compte (donc quel
     * jeton) elle relève, pour que {@see \App\Console\Commands\SyncPush}
     * la rejoue avec les bons identifiants plutôt qu'un jeton unique choisi
     * arbitrairement.
     */
    public function up(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->foreignId('desktop_provisioning_id')->nullable()->after('school_id')
                ->constrained('desktop_provisioning')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desktop_provisioning_id');
        });
    }
};
