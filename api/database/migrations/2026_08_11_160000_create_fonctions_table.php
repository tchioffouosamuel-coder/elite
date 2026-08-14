<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Référentiel des fonctions du personnel, tenu par le super administrateur.
     *
     * La fonction était jusqu'ici saisie en texte libre sur chaque fiche, ce qui
     * laissait cohabiter « Enseignant », « enseignant » et « Ensiegnant » sans
     * que rien ne les rapproche — et obligeait le tableau de bord à compter les
     * enseignants par comparaison de chaîne. Un référentiel commun aux trois
     * écoles du complexe rend ces regroupements fiables.
     *
     * Table globale, comme `niveaux` : les fonctions d'un complexe scolaire sont
     * les mêmes d'un établissement à l'autre.
     */
    public function up(): void
    {
        Schema::create('fonctions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('libelle_en')->nullable();
            // Sert aux regroupements du fichier du personnel et des statistiques.
            $table->enum('categorie', ['enseignement', 'direction', 'administration', 'appui'])
                ->default('enseignement');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonctions');
    }
};
