<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Justification d'absence déposée par un parent, par anticipation —
     * avant même que l'appel n'ait relevé l'absence. `presence_id` ne se
     * remplit qu'une fois rapprochée d'un pointage réel (cf.
     * `JustificationAbsenceService::appliquerSur`) : une justification pour
     * une date à venir n'a encore aucune séance à laquelle s'accrocher.
     */
    public function up(): void
    {
        Schema::create('justifications_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('motif', ['maladie', 'scolarite', 'permission']);
            $table->text('description')->nullable();
            $table->enum('statut', ['en_attente', 'appliquee'])->default('en_attente');
            $table->foreignId('presence_id')->nullable()->constrained('presences')->nullOnDelete();
            $table->timestamps();

            $table->index(['eleve_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications_absences');
    }
};
