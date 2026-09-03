<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jusqu'ici le poste desktop authentifiait automatiquement l'unique
     * compte lié, sans mot de passe (cf. `DesktopProvisioningController::session()`,
     * en cours de retrait). Plusieurs comptes pouvant désormais partager le
     * même poste, chacun a besoin de son propre mot de passe **local**
     * (distinct de celui du serveur distant, capturé une seule fois au
     * provisioning) pour ouvrir sa session.
     */
    public function up(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->string('password')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
