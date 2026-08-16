<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paie du personnel : rémunération contractuelle, bulletins mensuels et
     * décharges de salaire.
     *
     * Le bulletin de l'établissement (« BULLETIN DE PAIE / PAY SLIP ») porte
     * six gains (base, ancienneté, communication, transport, recherche &
     * leçon, performance), sept prélèvements à deux parts — salariale et
     * patronale — puis des déductions propres à la maison (raff, njangi,
     * prêt, absences). On stocke le détail ligne à ligne plutôt que les seuls
     * totaux : un bulletin doit rester réimprimable à l'identique des années
     * plus tard, même si les taux ont changé entre-temps.
     */
    public function up(): void
    {
        // Rémunération en vigueur. Historisée par date d'effet : une
        // augmentation ne doit pas réécrire les bulletins déjà émis.
        Schema::create('remunerations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->date('date_effet');
            $table->unsignedBigInteger('salaire_base')->default(0);
            $table->unsignedBigInteger('prime_anciennete')->default(0);
            $table->unsignedBigInteger('prime_communication')->default(0);
            $table->unsignedBigInteger('prime_transport')->default(0);
            $table->unsignedBigInteger('prime_recherche')->default(0);
            $table->unsignedBigInteger('prime_performance')->default(0);
            $table->string('categorie')->nullable();
            $table->timestamps();

            $table->index(['personnel_id', 'date_effet']);
        });

        Schema::create('bulletins_paie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->string('numero')->unique();
            // Période couverte : le bulletin est mensuel mais les dates réelles
            // figurent sur le document (« Période du … au … »).
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->date('periode_debut');
            $table->date('periode_fin');

            $table->unsignedSmallInteger('jours_ouvrables')->default(0);
            $table->unsignedSmallInteger('jours_travailles')->default(0);

            $table->unsignedBigInteger('salaire_brut')->default(0);
            $table->unsignedBigInteger('net_taxable')->default(0);
            $table->unsignedBigInteger('charges_salariales')->default(0);
            $table->unsignedBigInteger('charges_patronales')->default(0);
            // Déductions maison, hors barème légal.
            $table->unsignedBigInteger('deduction_absences')->default(0);
            $table->unsignedBigInteger('deduction_raff')->default(0);
            $table->unsignedBigInteger('deduction_njangi')->default(0);
            $table->unsignedBigInteger('deduction_pret')->default(0);
            $table->unsignedBigInteger('deduction_autre')->default(0);
            $table->unsignedBigInteger('net_a_payer')->default(0);

            /*
             * `brouillon` tant que la paie se prépare, `valide` une fois
             * arrêtée, `paye` quand l'agent a émargé. Seul un brouillon se
             * recalcule : un bulletin remis ne change plus.
             */
            $table->enum('statut', ['brouillon', 'valide', 'paye'])->default('brouillon');
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->nullable();
            $table->date('date_paiement')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            // Décharge : l'agent atteste avoir perçu son salaire.
            $table->timestamp('emarge_le')->nullable();
            $table->string('emargement_reference')->nullable();

            $table->timestamps();
            $table->unique(['personnel_id', 'annee', 'mois'], 'bulletins_paie_unique_periode');
            $table->index(['school_id', 'annee', 'mois']);
        });

        Schema::create('bulletin_paie_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_paie_id')->constrained('bulletins_paie')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->enum('type', ['gain', 'retenue']);
            $table->string('libelle');
            $table->string('libelle_en')->nullable();
            $table->unsignedBigInteger('base')->default(0);
            // Taux au millième près : la taxe de développement local vaut
            // 0,38 %, le crédit foncier 1 %, la pension vieillesse 4,2 %.
            $table->decimal('taux_salarial', 6, 4)->nullable();
            $table->decimal('taux_patronal', 6, 4)->nullable();
            $table->unsignedBigInteger('montant_salarial')->default(0);
            $table->unsignedBigInteger('montant_patronal')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_paie_lignes');
        Schema::dropIfExists('bulletins_paie');
        Schema::dropIfExists('remunerations');
    }
};
