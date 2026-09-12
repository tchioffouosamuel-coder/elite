<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // pause ou activite
            $table->string('nom');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->json('jours');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'type']);
        });

        Schema::create('emploi_du_temps_element_classe', function (Blueprint $table) {
            $table->foreignId('emploi_du_temps_element_id')->constrained('emploi_du_temps_elements')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->primary(['emploi_du_temps_element_id', 'classe_id']);
        });

        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->dropForeign(['classe_matiere_id']);
            $table->foreignId('classe_matiere_id')->nullable()->change();
            $table->string('type', 20)->default('cours')->after('classe_matiere_id');
            $table->string('libelle')->nullable()->after('type');
            $table->foreignId('emploi_du_temps_element_id')->nullable()->after('libelle')->constrained('emploi_du_temps_elements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->dropForeign(['emploi_du_temps_element_id']);
            $table->dropColumn(['emploi_du_temps_element_id', 'libelle', 'type']);
            $table->dropForeign(['classe_matiere_id']);
            $table->foreignId('classe_matiere_id')->nullable(false)->constrained('classe_matieres')->change();
        });

        Schema::dropIfExists('emploi_du_temps_element_classe');
        Schema::dropIfExists('emploi_du_temps_elements');
    }
};
