<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point de vente des fournitures scolaires : ce qui sort du stock et se
 * facture (`ventes_fournitures`), et ce qui y entre avec son coût
 * (`entrees_stock`).
 *
 * Une vente ne se supprime pas — la facture remise porte un numéro de série,
 * seule une annulation tracée la neutralise et remet la quantité en stock.
 * C'est le même principe que le versement de scolarité, pour la même raison :
 * un document remis au public ne peut pas disparaître du registre.
 *
 * Les lignes recopient le libellé et le prix pratiqués au moment de la vente
 * plutôt que de les relire sur l'article : le prix d'un cahier change en cours
 * d'année, et une facture réimprimée doit rester celle qui a été remise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes_fournitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained()->nullOnDelete();
            $table->string('numero_facture')->unique();
            $table->date('date_vente');
            $table->unsignedInteger('montant');
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->default('especes');
            // Acheteur : un élève quand le comptoir le renseigne — pour savoir
            // qui a pris quoi — mais la vente reste au comptant dans tous les cas.
            $table->foreignId('eleve_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client')->nullable();
            $table->foreignId('vendu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'date_vente']);
        });

        Schema::create('vente_fourniture_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_fourniture_id')->constrained('ventes_fournitures')->cascadeOnDelete();
            // L'article peut disparaître de l'inventaire ; la ligne facturée
            // reste lisible grâce au libellé recopié.
            $table->foreignId('inventaire_article_id')->nullable()->constrained('inventaire_articles')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('prix_unitaire');
            // Coût unitaire au moment de la sortie : sans lui, la marge d'une
            // vente ancienne se recalculerait au coût d'aujourd'hui.
            $table->unsignedInteger('cout_unitaire')->nullable();
            $table->timestamps();
        });

        Schema::create('entrees_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventaire_article_id')->constrained('inventaire_articles')->cascadeOnDelete();
            $table->date('date_entree');
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('cout_unitaire');
            $table->string('fournisseur')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'date_entree']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrees_stock');
        Schema::dropIfExists('vente_fourniture_lignes');
        Schema::dropIfExists('ventes_fournitures');
    }
};
