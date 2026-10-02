<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Progression du premier clonage d'une école, lot par lot (cf.
     * `SyncPull::tirerEcole()`) : chaque lot d'entités avance avec son propre
     * curseur, que `curseur_sync` — unique pour toute l'école — ne peut pas
     * porter. Vidée une fois le clonage terminé.
     */
    public function up(): void
    {
        Schema::table('desktop_provisioning_ecoles', function (Blueprint $table) {
            $table->json('progression_clonage')->nullable()->after('curseur_sync');
        });
    }

    public function down(): void
    {
        Schema::table('desktop_provisioning_ecoles', function (Blueprint $table) {
            $table->dropColumn('progression_clonage');
        });
    }
};
