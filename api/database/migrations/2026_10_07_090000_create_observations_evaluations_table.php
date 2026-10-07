<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->nullable()->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('classe_competence_id')->nullable()->constrained('classe_competences')->cascadeOnDelete();
            $table->text('texte')->nullable();
            $table->timestamps();

            $table->unique(
                ['eleve_id', 'trimestre_id', 'classe_matiere_id'],
                'observations_evaluations_matiere_unique',
            );
            $table->unique(
                ['eleve_id', 'trimestre_id', 'classe_competence_id'],
                'observations_evaluations_competence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations_evaluations');
    }
};
