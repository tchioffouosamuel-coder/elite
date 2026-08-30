<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vente de denrées alimentaires à l'école — tableau 28 du rapport de rentrée MINEDUB. */
    public function up(): void
    {
        Schema::create('ventes_denrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->string('nature');
            $table->string('vendeur_nom')->nullable();
            $table->boolean('dossier_medical_ok')->nullable();
            $table->unsignedBigInteger('frais_verses')->default(0);
            $table->string('gestion_frais')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes_denrees');
    }
};
