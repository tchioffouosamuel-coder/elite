<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('departement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('matricule')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->string('fonction'); // ex: Enseignant, Surveillant Général, Économe, Gardien
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->date('date_embauche')->nullable();
            $table->enum('statut', ['actif', 'ex_employe'])->default('actif');
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'matricule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnels');
    }
};
