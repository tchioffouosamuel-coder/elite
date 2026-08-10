<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Affectation d'une matière à une classe : c'est ici, et non sur la
     * matière elle-même, que vivent le coefficient, l'enseignant assigné
     * et le quota horaire — comme dans _smapp ({classe}_matieres), mais
     * avec de vraies colonnes/FK plutôt que du texte/JSON.
     */
    public function up(): void
    {
        Schema::create('classe_matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->decimal('coefficient', 3, 1)->default(1);
            $table->unsignedSmallInteger('quota_horaire')->nullable();
            $table->unsignedTinyInteger('groupe')->default(1); // bloc d'affichage sur le bulletin
            $table->text('competences')->nullable();
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();
            $table->unique(['classe_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_matieres');
    }
};
