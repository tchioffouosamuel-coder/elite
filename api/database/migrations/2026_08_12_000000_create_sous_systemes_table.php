<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sous_systemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code'); // ex: FR, EN, BI
            $table->string('nom'); // ex: Francophone, Anglophone, Bilingue
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sous_systemes');
    }
};
