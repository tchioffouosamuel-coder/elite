<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleve_tuteur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->string('lien_parente')->nullable(); // père, mère, tuteur légal...
            $table->boolean('is_principal')->default(false);
            $table->timestamps();
            $table->unique(['eleve_id', 'tuteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleve_tuteur');
    }
};
