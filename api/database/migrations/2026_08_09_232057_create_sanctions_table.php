<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reprend le catalogue de types de _smapp (corvées / exclusion /
     * exclusion+corvées / exclusion définitive), mais en colonnes réelles
     * (type, durée, motif) plutôt qu'une chaîne "label, motif" packée.
     */
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->enum('type', ['corvee', 'exclusion_temporaire', 'exclusion_definitive', 'autre']);
            $table->unsignedTinyInteger('duree_jours')->nullable();
            $table->text('motif');
            $table->date('date_sanction');
            $table->foreignId('enregistre_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
