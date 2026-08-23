<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes libres de la fiche de progression : jusqu'à dix par matière/classe,
 * pour ce que les deux gabarits de l'établissement ne prévoient pas (une
 * colonne « Vocabulaire », « Support numérique »…).
 *
 * Même schéma que `champs_personnalises` (Ma journée) — même besoin, une
 * liste ordonnée propre à une affectation classe↔matière — à ceci près que la
 * fiche de progression n'a pas de notion de type (texte/nombre/case) : ses
 * colonnes sont toutes du texte libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_colonnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->string('libelle', 60);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
            $table->index(['classe_matiere_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_colonnes');
    }
};
