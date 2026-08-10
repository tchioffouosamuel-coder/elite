<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre'); // 1..num_sequences (école.settings.num_sequences)
            $table->string('libelle'); // Séquence 1, Séquence 2, ...
            $table->timestamps();
            $table->unique(['trimestre_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
