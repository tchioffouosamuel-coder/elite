<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brouillon de préinscription soumis par un parent — pour un enfant déjà
     * inscrit (révision de sa fiche + versement) ou pour un nouvel enfant.
     *
     * Les informations proposées vivent en JSON plutôt que dans des colonnes
     * dupliquant `eleves`/`tuteurs` : ce n'est qu'une proposition en attente
     * d'examen, jamais appliquée telle quelle. `PreinscriptionService::valider()`
     * est le seul point qui les fait passer dans les tables réelles — un
     * parent ne modifie jamais directement la fiche de son enfant.
     */
    public function up(): void
    {
        Schema::create('preinscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->cascadeOnDelete();
            $table->enum('type', ['existant', 'nouveau']);
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('en_attente');

            $table->json('donnees_eleve');
            $table->json('donnees_tuteurs');
            // Note du parent à l'admin — typiquement pour signaler une erreur
            // sur la classe, qu'il ne peut pas corriger lui-même.
            $table->text('note_admin')->nullable();

            $table->unsignedBigInteger('montant_verser')->nullable();
            $table->string('mode_versement')->nullable();
            $table->string('reference_externe')->nullable();
            $table->json('rubriques_versement')->nullable();
            $table->foreignId('versement_id')->nullable()->constrained('versements')->nullOnDelete();

            $table->string('motif_rejet')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preinscriptions');
    }
};
