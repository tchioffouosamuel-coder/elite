<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contestation d'une note ou d'une décision, remontée par un tuteur ou un
     * élève auprès de l'administration — celle-ci saisit la réclamation pour
     * son compte, faute de portail parent/élève pour le faire lui-même.
     */
    public function up(): void
    {
        Schema::create('revendications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            // Uniquement pour une contestation de note : la matière visée.
            $table->foreignId('classe_matiere_id')->nullable()->constrained('classe_matieres')->nullOnDelete();
            $table->foreignId('trimestre_id')->nullable()->constrained('trimestres')->nullOnDelete();
            $table->enum('type', ['note', 'decision', 'autre']);
            $table->string('objet');
            $table->text('motif');
            $table->enum('statut', ['en_attente', 'en_cours', 'resolue', 'rejetee'])->default('en_attente');
            $table->text('decision')->nullable();
            $table->date('date_reception');
            $table->date('date_traitement')->nullable();
            $table->foreignId('enregistre_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revendications');
    }
};
