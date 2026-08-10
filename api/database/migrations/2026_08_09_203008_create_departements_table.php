<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nom'); // ex: Sciences, Lettres, Administration
            $table->timestamps();
            $table->unique(['school_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
