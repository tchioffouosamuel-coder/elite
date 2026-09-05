<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échéancier planifié d'une avance : une ligne par mois, montant que
 * l'employé a choisi de voir débité ce mois-là — contrairement à
 * `avance_remboursements` qui ne trace que les retenues réellement opérées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avance_salaire_id')->constrained('avances_salaire')->cascadeOnDelete();
            $table->date('mois');
            $table->unsignedInteger('montant_prevu');
            $table->timestamps();

            $table->index(['avance_salaire_id', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_echeances');
    }
};
