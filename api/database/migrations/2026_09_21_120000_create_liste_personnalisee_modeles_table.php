<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles réutilisables des listes personnalisées hors classes : enseignants
 * et transport partagent le même schéma (titre bilingue + colonnes), tout en
 * restant séparés par `domaine` pour éviter de mélanger les choix de colonnes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liste_personnalisee_modeles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domaine', 40);
            $table->string('titre_fr');
            $table->string('titre_en');
            $table->json('colonnes');
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id', 'domaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liste_personnalisee_modeles');
    }
};
