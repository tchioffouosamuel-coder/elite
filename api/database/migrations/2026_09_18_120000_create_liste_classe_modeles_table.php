<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle réutilisable de « liste personnalisée de classe » : un titre
 * bilingue et un jeu de colonnes choisis une fois par un utilisateur, qu'il
 * peut réappliquer à n'importe quelle classe sans tout ressaisir. La classe
 * elle-même n'est jamais enregistrée dans le modèle — elle se choisit à
 * chaque génération — pour qu'un même modèle serve à toutes les classes.
 *
 * `moyenne_type` ne mémorise que le TYPE de période (trimestre / séquence /
 * annuelle), pas la période exacte : un trimestre ou une séquence appartient
 * à une année scolaire précise, donc à un modèle réutilisable d'une année sur
 * l'autre ne peut pas la figer — elle se choisit fraîchement à chaque
 * génération.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liste_classe_modeles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titre_fr');
            $table->string('titre_en');
            $table->json('colonnes');
            $table->string('moyenne_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liste_classe_modeles');
    }
};
