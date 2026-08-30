<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonctionnement du conseil d'école (tableau 29) et de l'APEE (tableau
     * 30) du rapport de rentrée MINEDUB. Une ligne par école et par année
     * scolaire pour chacun — le bureau se renouvelle avec le mandat, pas
     * en cours d'année, donc pas de table d'historique séparée.
     */
    public function up(): void
    {
        Schema::create('conseils_ecole', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->boolean('existe')->default(false);
            $table->date('date_ag_elective')->nullable();
            $table->string('duree_mandat')->nullable();
            $table->year('fin_mandat')->nullable();
            $table->string('president_nom')->nullable();
            $table->string('president_fonction')->nullable();
            $table->string('president_telephone')->nullable();
            $table->string('statut_projet_ecole')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'annee_scolaire_id']);
        });

        Schema::create('apee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->boolean('legalisee')->default(false);
            $table->date('date_legalisation')->nullable();
            $table->string('numero_recepisse')->nullable();
            $table->string('banque')->nullable();
            $table->string('numero_compte')->nullable();
            $table->string('president_nom')->nullable();
            $table->string('president_fonction')->nullable();
            $table->string('president_telephone')->nullable();
            $table->date('date_ag_elective')->nullable();
            $table->year('fin_mandat')->nullable();
            $table->unsignedInteger('taux_par_eleve')->nullable();
            $table->unsignedBigInteger('montant_percu')->default(0);
            $table->unsignedBigInteger('montant_depense')->default(0);
            $table->text('realisations')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apee');
        Schema::dropIfExists('conseils_ecole');
    }
};
