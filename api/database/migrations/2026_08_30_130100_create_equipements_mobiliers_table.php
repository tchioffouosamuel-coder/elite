<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Équipements et mobilier de l'école — tableaux 19 (état des lieux) et 20
     * (besoins) du rapport de rentrée MINEDUB. `nature` reste en chaîne
     * libre plutôt qu'en enum : la liste du canevas (tables-bancs, tableaux
     * noirs, armoires, ordinateurs…) varie d'une école à l'autre et n'a pas
     * besoin d'être contrainte côté base.
     */
    public function up(): void
    {
        Schema::create('equipements_mobiliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nature');
            $table->unsignedInteger('quantite')->default(0);
            $table->unsignedInteger('besoin_quantite')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'nature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements_mobiliers');
    }
};
