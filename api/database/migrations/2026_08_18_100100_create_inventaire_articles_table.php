<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaire du matériel de l'établissement : mobilier, informatique,
 * pédagogique... un article regroupe une quantité identique dans un même
 * état et un même lieu plutôt qu'une ligne par bien physique — c'est ce que
 * l'économe compte réellement lors d'un inventaire (« 40 tables en bon état
 * en CM2-A »), pas un numéro de série par table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaire_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->enum('categorie', ['mobilier', 'informatique', 'pedagogique', 'sport', 'autre'])->default('autre');
            $table->unsignedInteger('quantite')->default(1);
            $table->enum('etat', ['bon', 'moyen', 'mauvais', 'hors_service'])->default('bon');
            $table->string('localisation')->nullable();
            $table->unsignedInteger('valeur_unitaire')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_articles');
    }
};
