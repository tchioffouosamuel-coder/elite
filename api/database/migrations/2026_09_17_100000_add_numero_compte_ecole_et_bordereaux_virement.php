<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'il manquait pour que le bordereau de virement soit directement la
 * lettre envoyée à la banque, et non un tableau à recopier dedans à la main :
 *
 *  - le numéro de compte de l'ÉTABLISSEMENT dans chaque banque (celui débité,
 *    distinct du numéro de compte du personnel, déjà porté par `personnels`) ;
 *  - un numéro d'ordre stable par mois : la lettre en porte un
 *    (« N° 010/06/2026 »), commun à toutes les banques de ce mois, qui ne doit
 *    pas changer si le PDF est régénéré plusieurs fois avant l'envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banques', function (Blueprint $table) {
            $table->string('numero_compte_ecole')->nullable()->after('code');
        });

        Schema::create('bordereaux_virement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->unsignedInteger('numero');
            $table->timestamps();

            $table->unique(['school_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bordereaux_virement');
        Schema::table('banques', fn (Blueprint $table) => $table->dropColumn('numero_compte_ecole'));
    }
};
