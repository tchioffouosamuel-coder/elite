<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le transport scolaire se paie au mois, indépendamment de la scolarité :
     * une famille peut être à jour de sa scolarité et devoir plusieurs mois de
     * bus, ou l'inverse. Chaque versement porte donc son propre reçu, dans son
     * propre registre — jamais mélangé aux lignes de `versements`, qui ne
     * couvrent plus que scolarité, frais annexes et reliquat.
     */
    public function up(): void
    {
        Schema::create('bus_versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_affectation_id')->constrained('bus_affectations')->cascadeOnDelete();
            // Premier jour du mois couvert par ce versement (ex. 2026-09-01).
            $table->date('mois');
            $table->string('numero_recu')->unique();
            $table->date('date_versement');
            $table->unsignedBigInteger('montant');
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->default('especes');
            $table->string('reference_externe')->nullable();
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            // Même principe que pour la scolarité : un encaissement ne se
            // supprime pas, il s'annule en gardant trace de qui, quand, pourquoi.
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();

            $table->timestamps();
            $table->index(['bus_affectation_id', 'mois']);
            $table->index(['school_id', 'date_versement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_versements');
    }
};
