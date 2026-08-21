<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_avance_salaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->unsignedInteger('nombre_mois');
            $table->string('motif')->nullable();
            $table->string('statut')->default('en_attente');
            $table->string('motif_rejet')->nullable();
            // Renseigné à la validation : l'avance réellement créée par
            // AvanceSalaireService::accorder(), pour remonter au dossier depuis
            // la demande d'origine.
            $table->foreignId('avance_salaire_id')->nullable()->constrained('avances_salaire')->nullOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();

            $table->index(['personnel_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_avance_salaire');
    }
};
