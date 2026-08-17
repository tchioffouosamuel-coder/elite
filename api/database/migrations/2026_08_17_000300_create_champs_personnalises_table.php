<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tableaux d'informations spécifiques : chaque matière peut définir ses
     * propres champs de saisie (ex. « projet de groupe », « travaux pratiques
     * réalisés »), remplis séance par séance dans `seances.donnees_personnalisees`.
     */
    public function up(): void
    {
        Schema::create('champs_personnalises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->string('libelle');
            $table->enum('type', ['texte', 'nombre', 'case'])->default('texte');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('champs_personnalises');
    }
};
