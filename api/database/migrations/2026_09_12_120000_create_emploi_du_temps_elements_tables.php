<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ne supporte pas les DDL transactionnelles : un déploiement
        // interrompu après la première table créée (pod tué, timeout) laisse
        // ces `CREATE TABLE` en place sans que la migration soit enregistrée
        // comme jouée — le rejeu doit donc pouvoir sauter ce qui existe déjà
        // plutôt que de repartir de zéro à chaque tentative.
        if (! Schema::hasTable('emploi_du_temps_elements')) {
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
        }

        if (! Schema::hasTable('emploi_du_temps_element_classe')) {
            Schema::create('emploi_du_temps_element_classe', function (Blueprint $table) {
                $table->foreignId('emploi_du_temps_element_id');
                $table->foreign('emploi_du_temps_element_id', 'edt_elem_classe_element_fk')
                    ->references('id')->on('emploi_du_temps_elements')->cascadeOnDelete();
                $table->foreignId('classe_id');
                $table->foreign('classe_id', 'edt_elem_classe_classe_fk')
                    ->references('id')->on('classes')->cascadeOnDelete();
                $table->primary(['emploi_du_temps_element_id', 'classe_id']);
            });
        }

        // Un seul `Schema::table(...)` se compile en une unique instruction
        // ALTER TABLE côté MySQL : soit toutes ces colonnes existent déjà
        // (bloc déjà rejoué avec succès), soit aucune — `type` sert de témoin.
        if (! Schema::hasColumn('emplois_du_temps', 'type')) {
            Schema::table('emplois_du_temps', function (Blueprint $table) {
                $table->dropForeign(['classe_matiere_id']);
                $table->foreignId('classe_matiere_id')->nullable()->change();
                $table->foreign('classe_matiere_id')->references('id')->on('classe_matieres')->nullOnDelete();
                $table->string('type', 20)->default('cours')->after('classe_matiere_id');
                $table->string('libelle')->nullable()->after('type');
                $table->foreignId('emploi_du_temps_element_id')->nullable()->after('libelle');
                $table->foreign('emploi_du_temps_element_id', 'edt_element_fk')
                    ->references('id')->on('emploi_du_temps_elements')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->dropForeign('edt_element_fk');
            $table->dropColumn(['emploi_du_temps_element_id', 'libelle', 'type']);
            $table->dropForeign(['classe_matiere_id']);
            $table->foreignId('classe_matiere_id')->nullable(false)->change();
            $table->foreign('classe_matiere_id')->references('id')->on('classe_matieres')->cascadeOnDelete();
        });

        Schema::dropIfExists('emploi_du_temps_element_classe');
        Schema::dropIfExists('emploi_du_temps_elements');
    }
};
