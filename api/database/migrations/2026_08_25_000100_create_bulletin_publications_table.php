<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('publie_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamp('publie_le');
            $table->timestamps();

            $table->unique(['trimestre_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_publications');
    }
};
