<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->nullable()->constrained('trimestres')->nullOnDelete();
            $table->foreignId('emploi_du_temps_id')->nullable()->constrained('emplois_du_temps')->nullOnDelete();
            $table->date('date_seance');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle')->nullable();
            $table->text('contenu')->nullable(); // progression pédagogique effectivement couverte
            $table->enum('statut', ['prevue', 'effectuee', 'annulee'])->default('prevue');
            $table->timestamps();
            $table->index(['classe_id', 'date_seance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
