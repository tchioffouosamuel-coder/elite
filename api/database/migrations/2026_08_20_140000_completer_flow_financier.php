<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les quatre pièces qui manquaient au circuit financier.
 *
 *  1. **Immobilisations.** Sortir la construction du résultat ne suffisait
 *     pas : elle en disparaissait purement, au lieu d'y revenir étalée. Le
 *     compte 699 existait, vide. Une immobilisation porte le montant, la date
 *     de mise en service et la durée sur laquelle il se répartit.
 *
 *  2. **Vacation horaire.** Au collège technique, 24 agents sur 32 sont payés
 *     à l'heure enseignée — 1 000 à 1 100 F. La rémunération n'était que
 *     mensuelle : ces agents n'avaient aucun support.
 *
 *  3. **Heures du bulletin.** Le registre porte en tête les jours ouvrables et
 *     les heures du mois ; sans elles, une vacation ne se justifie pas.
 *
 *  4. **Domiciliation bancaire.** Le bordereau de virement se range par
 *     banque et porte le numéro de compte de chaque agent. Ni l'un ni l'autre
 *     n'était enregistré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immobilisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // La dépense d'origine, quand l'immobilisation naît d'un compte
            // classé en investissement : c'est elle qui porte le justificatif.
            $table->foreignId('depense_id')->nullable()->constrained('depenses')->nullOnDelete();
            $table->foreignId('compte_comptable_id')->nullable()->constrained('comptes_comptables')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->date('date_mise_en_service');
            // Durée d'amortissement en années. Un bâtiment s'étale sur vingt
            // ans par défaut ; une réfection sur moins.
            $table->unsignedSmallInteger('duree_annees');
            $table->timestamp('cede_le')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'date_mise_en_service']);
        });

        /*
         * Une dotation par immobilisation et par exercice, jamais deux : c'est
         * cette unicité qui rend le calcul rejouable sans doubler la charge.
         */
        Schema::create('amortissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immobilisation_id')->constrained('immobilisations')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_dotation');
            $table->timestamps();

            $table->unique(['immobilisation_id', 'annee_scolaire_id'], 'amortissements_unique_exercice');
        });

        Schema::table('remunerations', function (Blueprint $table) {
            // « mensuel » : le salaire négocié du primaire. « horaire » : la
            // vacation du technique, où seules les heures faites sont dues.
            $table->enum('mode', ['mensuel', 'horaire'])->default('mensuel')->after('date_effet');
            $table->unsignedInteger('taux_horaire')->nullable()->after('mode');
        });

        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->unsignedSmallInteger('heures')->nullable()->after('jours_travailles');
            $table->unsignedInteger('taux_horaire')->nullable()->after('heures');
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->string('banque')->nullable()->after('matricule');
            $table->string('numero_compte')->nullable()->after('banque');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', fn (Blueprint $table) => $table->dropColumn(['banque', 'numero_compte']));
        Schema::table('bulletins_paie', fn (Blueprint $table) => $table->dropColumn(['heures', 'taux_horaire']));
        Schema::table('remunerations', fn (Blueprint $table) => $table->dropColumn(['mode', 'taux_horaire']));
        Schema::dropIfExists('amortissements');
        Schema::dropIfExists('immobilisations');
    }
};
