<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programmation et taux d'exécution des activités pédagogiques (tableau
     * 23), de l'EPS (tableau 24) et de la FENASSCO (tableau 25) du rapport
     * de rentrée MINEDUB — une même forme (activité, prévu/fait, %) pour les
     * trois tableaux, distingués par `categorie`. `prevues`/`faites` portent
     * le décompte des tableaux 23-24 ; `taux_realisation` porte le
     * pourcentage saisi directement pour la FENASSCO (une progression
     * estimée, pas un ratio d'événements dénombrables).
     */
    public function up(): void
    {
        Schema::create('activites_rentree', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->enum('categorie', ['pedagogique', 'eps', 'fenassco']);
            $table->string('activite');
            $table->string('periode')->nullable();
            $table->string('objectifs_vises')->nullable();
            $table->unsignedInteger('prevues')->nullable();
            $table->unsignedInteger('faites')->nullable();
            $table->unsignedTinyInteger('taux_realisation')->nullable();
            $table->string('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activites_rentree');
    }
};
