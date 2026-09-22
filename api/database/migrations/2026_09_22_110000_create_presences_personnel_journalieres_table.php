<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences_personnel_journalieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->date('date_presence');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->string('source')->default('import_ocr');
            $table->timestamps();

            $table->unique(['school_id', 'personnel_id', 'date_presence'], 'presence_personnel_jour_unique');
            $table->index(['school_id', 'date_presence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_personnel_journalieres');
    }
};
