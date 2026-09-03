<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcours scolaire d'un élève, une ligne par année : c'est la table qui
 * répond à « dans quelle classe était cet élève telle année, avec quel
 * résultat ? » sans avoir à rouvrir l'archive pédagogique complète
 * (archives_classe_annee) — celle-ci reste le détail lourd (notes, absences),
 * celle-ci n'est que le résumé consultable pour chaque élève.
 *
 * `classe_id` peut devenir orphelin si la classe est supprimée (jamais en
 * usage normal, les classes sont des gabarits permanents) : `classe_nom` est
 * donc un instantané texte, la seule source fiable une fois l'année passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historiques_scolarite_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('classe_nom');
            $table->string('niveau_libelle')->nullable();
            $table->decimal('moyenne_annuelle', 5, 2)->nullable();
            $table->unsignedSmallInteger('rang_annuel')->nullable();
            $table->enum('decision', ['admis', 'redouble', 'exclu', 'diplome']);
            $table->boolean('gracie')->default(false);
            $table->text('motif')->nullable();
            $table->timestamps();

            $table->unique(['eleve_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historiques_scolarite_eleves');
    }
};
