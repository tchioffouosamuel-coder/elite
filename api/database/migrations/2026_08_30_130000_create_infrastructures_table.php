<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bâti et installations de l'école — tableau 18 du rapport de rentrée
     * MINEDUB. `materiau`/`etat` ne concernent que les salles de classe et le
     * bloc administratif (dur/semi-dur/matériaux provisoires × bon/assez-bon/
     * mauvais) ; les autres types (WC, clôture, point d'eau…) n'utilisent que
     * `quantite` — 0/1 pour une installation simplement présente ou non
     * (clôture, électricité), un décompte pour les autres (WC, logements).
     */
    public function up(): void
    {
        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'salle_classe', 'bloc_administratif', 'wc', 'cloture',
                'point_eau', 'electricite', 'aire_jeu', 'logement_maitre', 'autre',
            ]);
            $table->string('libelle')->nullable();
            $table->enum('materiau', ['dur', 'semi_dur', 'provisoire'])->nullable();
            $table->enum('etat', ['bon', 'assez_bon', 'mauvais'])->nullable();
            $table->unsignedInteger('quantite')->default(1);
            $table->unsignedInteger('besoin_quantite')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructures');
    }
};
