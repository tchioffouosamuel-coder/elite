<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le tarif du bus dépend de l'option choisie, pas seulement de
     * l'itinéraire : un aller simple coûte moins qu'un aller-retour.
     */
    public function up(): void
    {
        Schema::table('bus_affectations', function (Blueprint $table) {
            $table->enum('option_trajet', ['aller_simple', 'retour_simple', 'aller_retour'])
                ->default('aller_retour')
                ->after('arret_id');
        });
    }

    public function down(): void
    {
        Schema::table('bus_affectations', function (Blueprint $table) {
            $table->dropColumn('option_trajet');
        });
    }
};
