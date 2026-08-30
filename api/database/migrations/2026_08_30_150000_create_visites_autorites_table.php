<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Visites d'autorités administratives/pédagogiques — tableau 22 du rapport de rentrée MINEDUB. */
    public function up(): void
    {
        Schema::create('visites_autorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->date('date_visite');
            $table->string('qualite_autorite');
            $table->string('nature_visite')->nullable();
            $table->string('objectifs')->nullable();
            $table->string('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_autorites');
    }
};
