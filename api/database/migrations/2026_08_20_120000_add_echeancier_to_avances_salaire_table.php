<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avances_salaire', function (Blueprint $table) {
            // Nullables : les avances déjà accordées avant l'ajout de
            // l'échéancier n'en portent pas — seules les nouvelles en sont
            // dotées, calculées et plafonnées par AvanceSalaireService.
            $table->unsignedInteger('nombre_mois')->nullable()->after('montant');
            $table->unsignedInteger('mensualite')->nullable()->after('nombre_mois');
        });
    }

    public function down(): void
    {
        Schema::table('avances_salaire', function (Blueprint $table) {
            $table->dropColumn(['nombre_mois', 'mensualite']);
        });
    }
};
