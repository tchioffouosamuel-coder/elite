<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Heures d'absence cumulées par élève × trimestre (comme _smapp :
     * saisie manuelle par le Surveillant Général de deux compteurs par
     * trimestre, pas un journal par créneau/jour).
     */
    public function up(): void
    {
        Schema::create('absence_trimestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->decimal('heures_justifiees', 5, 1)->default(0);
            $table->decimal('heures_non_justifiees', 5, 1)->default(0);
            $table->foreignId('mis_a_jour_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();
            $table->unique(['eleve_id', 'trimestre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_trimestres');
    }
};
