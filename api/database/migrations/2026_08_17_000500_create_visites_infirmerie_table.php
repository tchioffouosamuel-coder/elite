<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visites_infirmerie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->dateTime('date_visite');
            $table->text('raison');
            $table->text('soins_prodiges');
            $table->unsignedInteger('cout_soins')->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('enregistre_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();

            $table->index(['classe_id', 'date_visite']);
            $table->index(['eleve_id', 'date_visite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_infirmerie');
    }
};
