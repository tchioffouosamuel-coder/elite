<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifications_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->json('donnees');
            $table->string('statut')->default('en_attente');
            $table->string('motif_rejet')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();

            $table->index(['eleve_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifications_eleves');
    }
};
