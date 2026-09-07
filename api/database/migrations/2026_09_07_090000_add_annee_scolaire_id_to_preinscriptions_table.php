<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une préinscription confirme la présence pour UNE année scolaire précise —
 * jusqu'ici, rien ne le disait, ce qui empêchait de savoir quels anciens
 * élèves se sont déjà réinscrits pour la campagne en cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->foreignId('annee_scolaire_id')->nullable()->after('school_id')->constrained('annee_scolaires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annee_scolaire_id');
        });
    }
};
