<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espace d'évaluation (point 24 des retours) : avis libres soumis par un
     * parent, un enseignant ou un membre du personnel administratif, que
     * l'administration consulte et filtre. L'auteur est toujours un `User` —
     * son rôle au moment de la lecture (`user->getRoleNames()`) dit de quel
     * côté vient l'avis, comme pour `observations` : inutile de le dupliquer
     * dans une colonne qui pourrait désynchroniser si le rôle change.
     */
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sujet', 150)->nullable();
            $table->text('contenu');
            $table->unsignedTinyInteger('note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
