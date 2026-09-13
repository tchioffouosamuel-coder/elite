<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->string('lieu_ramassage')->nullable()->after('lieu_dit');
            $table->string('lieu_depot')->nullable()->after('lieu_ramassage');
            $table->unsignedInteger('tarif_aller_simple')->nullable()->after('heure_passage');
            $table->unsignedInteger('tarif_retour_simple')->nullable()->after('tarif_aller_simple');
            $table->unsignedInteger('tarif_aller_retour')->nullable()->after('tarif_retour_simple');
        });
    }

    public function down(): void
    {
        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->dropColumn([
                'lieu_ramassage',
                'lieu_depot',
                'tarif_aller_simple',
                'tarif_retour_simple',
                'tarif_aller_retour',
            ]);
        });
    }
};
