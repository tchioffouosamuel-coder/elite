<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Même échéancier que `avance_echeances`, proposé par l'employé à la demande, avant validation par un admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_avance_echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_avance_salaire_id')->constrained('demandes_avance_salaire')->cascadeOnDelete();
            $table->date('mois');
            $table->unsignedInteger('montant_prevu');
            $table->timestamps();

            $table->index(['demande_avance_salaire_id', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_avance_echeances');
    }
};
