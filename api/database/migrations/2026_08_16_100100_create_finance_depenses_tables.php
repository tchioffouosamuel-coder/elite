<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dépenses et plan comptable.
     *
     * Le complexe tient déjà un plan de comptes à neuf classes (« Accounting
     * situation ») : capitaux, immobilisations, stocks, tiers, trésorerie,
     * charges, produits, hors activité ordinaire, analytique. On le reprend
     * tel quel plutôt que d'inventer un classement maison — c'est celui que
     * comprend le comptable de l'établissement, et celui qu'attendent les
     * états à produire.
     *
     * Le journal (`ecritures_comptables`) enregistre le mouvement de chaque
     * opération, quelle qu'en soit l'origine : encaissement de scolarité,
     * dépense, salaire versé. Sans lui, un bilan se recalculerait à chaque
     * fois en interrogeant trois tables sans lien entre elles.
     */
    public function up(): void
    {
        Schema::create('comptes_comptables', function (Blueprint $table) {
            $table->id();
            // Le plan est commun au complexe, comme les niveaux : les trois
            // écoles rendent des comptes sur la même nomenclature.
            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('libelle_en')->nullable();
            $table->unsignedTinyInteger('classe');
            $table->enum('sens', ['debit', 'credit'])->default('debit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->foreignId('compte_comptable_id')->nullable()->constrained('comptes_comptables')->nullOnDelete();
            $table->date('date_depense');
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->default('especes');
            $table->string('beneficiaire')->nullable();
            $table->string('reference_facture')->nullable();
            // Qui engage la dépense (le « Responsable » du tableau) et qui la
            // saisit : ce n'est pas toujours la même personne.
            $table->string('responsable')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('justificatif_path')->nullable();

            // Une dépense engagée n'est pas une dépense payée : le suivi de
            // trésorerie a besoin de distinguer les deux.
            $table->enum('statut', ['engagee', 'payee', 'annulee'])->default('payee');
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();

            $table->timestamps();
            $table->index(['school_id', 'date_depense']);
        });

        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->date('date_ecriture');
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->enum('sens', ['debit', 'credit']);
            $table->foreignId('compte_comptable_id')->nullable()->constrained('comptes_comptables')->nullOnDelete();
            // Pièce d'origine : versement, dépense, bulletin de paie…
            $table->nullableMorphs('origine');
            $table->timestamps();

            $table->index(['school_id', 'date_ecriture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecritures_comptables');
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('comptes_comptables');
    }
};
