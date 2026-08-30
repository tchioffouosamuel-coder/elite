<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assurance scolaire par groupe de niveaux (tableau 26 du rapport de
     * rentrée MINEDUB). Le regroupement du canevas (Niveau 1/2/3) ne
     * correspond pas au découpage `niveaux` de l'application — `libelle`
     * reste donc en chaîne libre plutôt qu'une clé étrangère, et l'effectif
     * est saisi ici plutôt que recalculé : c'est l'effectif assuré à la date
     * de la police, qui peut différer de l'effectif courant.
     */
    public function up(): void
    {
        Schema::create('assurances_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->string('libelle');
            $table->unsignedInteger('effectif')->default(0);
            $table->string('nom_assureur')->nullable();
            $table->string('numero_police')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assurances_scolaires');
    }
};
