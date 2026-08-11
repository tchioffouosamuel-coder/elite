<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programme d'enseignement annuel d'une matière dans une classe.
     *
     * Les matières ne se découpent pas toutes de la même façon : certaines
     * s'organisent en modules contenant des chapitres puis des leçons,
     * d'autres n'ont que des chapitres, d'autres une simple liste de leçons.
     * Un arbre auto-référencé accepte ces trois formes sans table par niveau
     * ni colonnes laissées vides.
     *
     * Seule la leçon porte une séquence cible : c'est l'unité que l'enseignant
     * déclare avoir traitée, et donc celle qui mesure l'avancement.
     */
    public function up(): void
    {
        Schema::create('progression_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('progression_items')->cascadeOnDelete();
            $table->enum('type', ['module', 'chapitre', 'lecon']);
            $table->string('titre');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            // Période à laquelle la leçon doit être traitée ; nulle sur les
            // modules et chapitres, qui ne sont que des regroupements.
            $table->foreignId('sequence_id')->nullable()->constrained('sequences')->nullOnDelete();
            $table->unsignedSmallInteger('duree_prevue')->nullable(); // heures
            $table->timestamps();
            $table->index(['classe_matiere_id', 'parent_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_items');
    }
};
