<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une note par élève × affectation classe-matière × séquence.
     * Contrairement à _smapp (JSON keyed by eval1..eval12 dans une colonne
     * partagée), chaque note est une ligne réelle : simple à interroger,
     * à agréger et à corriger sans reconstruire tout le blob JSON.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->decimal('valeur', 4, 2)->nullable(); // /20, pas de 0.25
            $table->foreignId('saisi_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();
            $table->unique(['eleve_id', 'classe_matiere_id', 'sequence_id'], 'notes_unique_cell');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
