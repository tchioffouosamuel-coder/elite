<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('matricule')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->enum('sexe', ['M', 'F']);
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite')->nullable();
            $table->string('adresse')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('redoublant')->default(false);
            $table->enum('statut', ['actif', 'parti', 'exclu'])->default('actif');
            $table->timestamps();
            $table->unique(['school_id', 'matricule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleves');
    }
};
