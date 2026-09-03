<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conseil de classe de fin d'année : un par (classe, année scolaire), qui
 * décide qui passe, qui redouble, et vers quelle classe (choisie ici, pas
 * configurée une fois pour toutes — une même classe peut alimenter plusieurs
 * classes parallèles au niveau supérieur selon les années).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conseils_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('seuil_moyenne', 4, 2)->default(10);
            // Requis si le seuil diffère du défaut de l'école (passage_moyenne_min).
            $table->text('motif_seuil')->nullable();
            // Null = fin de cycle (pas de classe supérieure dans l'école) : les
            // admis sont diplômés plutôt que déplacés.
            $table->foreignId('classe_destination_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->enum('statut', ['brouillon', 'valide'])->default('brouillon');
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conseils_classe');
    }
};
