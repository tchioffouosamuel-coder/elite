<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrou de clôture : tant que `archivee_le` est nul, l'année n'est pas
 * intégralement archivée et « passer à l'année suivante » reste bloqué
 * (cf. AnneeScolaireController::basculer()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annee_scolaires', function (Blueprint $table) {
            $table->timestamp('archivee_le')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('annee_scolaires', function (Blueprint $table) {
            $table->dropColumn('archivee_le');
        });
    }
};
