<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande d'ajout d'un article d'inventaire, soumise par un employé
 * (typiquement un infirmier pour du matériel médical reçu) plutôt qu'écrite
 * directement dans `inventaire_articles` — même patron que
 * `demandes_avance_salaire` : rien n'atteint l'inventaire réel avant qu'un
 * titulaire de `inventaire.manage` ne valide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_articles_inventaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->json('donnees');
            $table->string('statut')->default('en_attente');
            $table->string('motif_rejet')->nullable();
            // Renseigné à la validation : l'article réellement créé par
            // InventaireService::creer(), pour remonter au catalogue depuis
            // la demande d'origine.
            $table->foreignId('inventaire_article_id')->nullable()->constrained('inventaire_articles')->nullOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();

            $table->index(['personnel_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_articles_inventaire');
    }
};
