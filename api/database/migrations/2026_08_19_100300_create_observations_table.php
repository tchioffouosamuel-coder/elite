<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fil d'observations sur un élève, partagé entre l'établissement et la
     * famille — l'auteur est toujours un `User` (parent ou personnel, les
     * deux s'authentifient de la même façon) : son rôle au moment de la
     * lecture suffit à savoir de quel côté vient la remarque, inutile de le
     * dupliquer dans une colonne.
     */
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamps();

            $table->index(['eleve_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
