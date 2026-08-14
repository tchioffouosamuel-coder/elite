<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Répartition du barème d'une matière du primaire entre ses volets
     * (oral, écrit, savoir-être, pratique) : `{"oral": 5, "ecrit": 10,
     * "savoir_etre": 5}`. Nulle pour les matières créées avant cette
     * colonne — `Matiere::repartitionVolets()` retombe alors sur une
     * répartition à parts égales, le comportement déjà en vigueur.
     */
    public function up(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->json('repartition_volets')->nullable()->after('evalue_pratique');
        });
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropColumn('repartition_volets');
        });
    }
};
