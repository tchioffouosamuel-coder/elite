<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // maternelle | primaire | college
            $table->string('name_fr');
            $table->string('name_en');
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Niveaux effectivement proposés par chaque établissement.
        Schema::create('school_niveau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'niveau_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_niveau');
        Schema::dropIfExists('niveaux');
    }
};
