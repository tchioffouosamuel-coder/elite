<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('professeur_principal_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->string('nom'); // ex: 6ème A, CM2 B
            $table->string('filiere')->nullable(); // ex: Général, Technique — surtout au Collège
            $table->unsignedInteger('capacite')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'annee_scolaire_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
