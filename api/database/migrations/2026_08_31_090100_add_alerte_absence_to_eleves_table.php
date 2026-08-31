<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marque le jour où l'alerte « 5 jours de cours consécutifs sans aucun
     * pointage » a été déclenchée pour l'élève — évite de renotifier
     * l'administration et de rebloquer le parent chaque jour tant que la
     * situation n'a pas changé. Remis à `null` dès qu'un pointage réapparaît,
     * pour qu'une nouvelle série d'absences puisse redéclencher l'alerte.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->date('alerte_absence_declenchee_le')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn('alerte_absence_declenchee_le');
        });
    }
};
