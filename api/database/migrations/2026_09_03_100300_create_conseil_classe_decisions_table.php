<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Décision par élève au sein d'un conseil de classe — `decision_defaut` est
 * le calcul automatique (moyenne annuelle vs seuil), `decision_finale` est ce
 * qui s'applique réellement une fois les ajustements du conseil pris en
 * compte (exclusion, grâce). Les deux sont conservées : c'est ce qui permet
 * d'afficher « gracié » plutôt que simplement « admis ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conseil_classe_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conseil_classe_id')->constrained('conseils_classe')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained()->cascadeOnDelete();
            $table->decimal('moyenne_annuelle', 5, 2)->nullable();
            $table->enum('decision_defaut', ['admis', 'redouble']);
            $table->enum('decision_finale', ['admis', 'redouble', 'exclu']);
            $table->boolean('gracie')->default(false);
            // Requis si exclu ou gracié.
            $table->text('motif')->nullable();
            $table->timestamps();

            $table->unique(['conseil_classe_id', 'eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conseil_classe_decisions');
    }
};
