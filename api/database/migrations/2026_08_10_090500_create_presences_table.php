<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->enum('statut', ['present', 'absent', 'retard', 'renvoye'])->default('present');
            $table->boolean('justifie')->default(false);
            $table->string('remarque')->nullable();
            $table->timestamps();
            $table->unique(['seance_id', 'eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
