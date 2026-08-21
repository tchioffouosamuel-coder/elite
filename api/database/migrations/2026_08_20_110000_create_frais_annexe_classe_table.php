<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circonscrit un frais annexe à certaines classes plutôt qu'à toute l'école.
 * Aucune ligne pour un frais = portée école entière (comportement d'origine,
 * préservé pour les frais déjà créés) ; une ou plusieurs lignes = limité à
 * cette classe, ou à ce groupe de classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais_annexe_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frais_annexe_id')->constrained('frais_annexes')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->unique(['frais_annexe_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais_annexe_classe');
    }
};
