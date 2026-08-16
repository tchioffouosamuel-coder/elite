<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encaissement des frais de scolarité et des frais annexes.
     *
     * Le tarif vit à deux niveaux. La **grille** donne le prix par classe pour
     * une année : c'est la référence de l'établissement. Le **dossier** de
     * l'élève en recopie le montant à l'inscription et devient ensuite la
     * seule vérité pour lui — un tarif révisé en cours d'année ne doit pas
     * réécrire rétroactivement ce qui a été annoncé aux familles, et chaque
     * élève peut porter sa remise ou son montant négocié.
     *
     * Les montants sont des entiers : le franc CFA n'a pas de subdivision, et
     * un décimal n'apporterait ici que des erreurs d'arrondi.
     */
    public function up(): void
    {
        // Tarif de référence par classe. `classe_id` nul = tarif par défaut de
        // l'école, appliqué aux classes sans ligne propre.
        Schema::create('grilles_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['school_id', 'annee_scolaire_id', 'classe_id'], 'grilles_frais_unique');
        });

        // Catalogue des frais annexes : tenue, livres, transport, examen…
        // Le reçu de l'établissement les appelle « accessoires ».
        Schema::create('frais_annexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            // Un frais obligatoire est ajouté d'office au dossier de chaque
            // élève ; les autres se rattachent au cas par cas.
            $table->boolean('obligatoire')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dossiers_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('montant_scolarite')->default(0);
            $table->unsignedBigInteger('remise')->default(0);
            // Reliquat de l'année précédente, repris à l'inscription : l'écran
            // de l'ancienne application l'appelle « dette scolarité ».
            $table->unsignedBigInteger('report_dette')->default(0);
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'eleve_id'], 'dossiers_scolarite_unique');
        });

        // Frais annexes réellement dus par l'élève. Le montant est recopié :
        // il peut être négocié, et le catalogue peut changer après coup.
        Schema::create('dossier_frais_annexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_scolarite_id')->constrained('dossiers_scolarite')->cascadeOnDelete();
            $table->foreignId('frais_annexe_id')->nullable()->constrained('frais_annexes')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });

        Schema::create('versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dossier_scolarite_id')->constrained('dossiers_scolarite')->cascadeOnDelete();
            $table->string('numero_recu')->unique();
            $table->date('date_versement');
            $table->unsignedBigInteger('montant');
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->default('especes');
            $table->string('reference_externe')->nullable();
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            /*
             * Un encaissement ne se supprime pas : le reçu remis à la famille
             * porte un numéro, et le faire disparaître du registre romprait la
             * traçabilité. Une erreur s'annule, en gardant trace de qui, quand
             * et pourquoi.
             */
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();

            $table->timestamps();
            $table->index(['school_id', 'date_versement']);
        });

        // Ventilation d'un versement : le reçu distingue l'historique de la
        // scolarité de celui des accessoires.
        Schema::create('versement_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versement_id')->constrained()->cascadeOnDelete();
            $table->enum('affectation', ['scolarite', 'frais_annexe', 'report_dette'])->default('scolarite');
            $table->foreignId('dossier_frais_annexe_id')->nullable()
                ->constrained('dossier_frais_annexes')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versement_lignes');
        Schema::dropIfExists('versements');
        Schema::dropIfExists('dossier_frais_annexes');
        Schema::dropIfExists('dossiers_scolarite');
        Schema::dropIfExists('frais_annexes');
        Schema::dropIfExists('grilles_frais');
    }
};
