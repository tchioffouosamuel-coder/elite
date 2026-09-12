<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tronc_commun_groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->string('nom');
            $table->timestamps();
            $table->index(['school_id', 'matiere_id', 'personnel_id']);
        });

        Schema::create('tronc_commun_groupe_classe', function (Blueprint $table) {
            $table->foreignId('tronc_commun_groupe_id')->constrained('tronc_commun_groupes')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->primary(['tronc_commun_groupe_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tronc_commun_groupe_classe');
        Schema::dropIfExists('tronc_commun_groupes');
    }
};
