<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matériel de l'inventaire consommé lors d'une visite. Le nom et le coût
 * unitaire sont recopiés au moment de l'usage (pas seulement référencés) :
 * si l'article est renommé ou son prix changé plus tard, l'état d'émargement
 * d'une visite passée ne doit pas se réécrire tout seul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visite_infirmerie_materiels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visite_infirmerie_id')->constrained('visites_infirmerie')->cascadeOnDelete();
            $table->foreignId('inventaire_article_id')->nullable()->constrained('inventaire_articles')->nullOnDelete();
            $table->string('nom');
            $table->unsignedInteger('quantite')->default(1);
            $table->unsignedInteger('cout_unitaire')->default(0);
            $table->unsignedInteger('cout')->default(0);
            $table->timestamps();

            $table->index('visite_infirmerie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visite_infirmerie_materiels');
    }
};
