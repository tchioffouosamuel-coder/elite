<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendrier_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('est_ouvert')->default(false);
            $table->string('motif')->nullable();
            $table->foreignId('sous_systeme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('niveau_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['annee_scolaire_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendrier_scolaires');
    }
};