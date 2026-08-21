<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Couleur du véhicule — c'est par elle qu'un parent le repère à la sortie, bien avant de lire une plaque. */
    public function up(): void
    {
        Schema::table('bus_vehicules', function (Blueprint $table) {
            $table->string('couleur', 30)->nullable()->after('marque');
        });
    }

    public function down(): void
    {
        Schema::table('bus_vehicules', function (Blueprint $table) {
            $table->dropColumn('couleur');
        });
    }
};
