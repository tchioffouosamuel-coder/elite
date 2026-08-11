<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leçons traitées au cours d'une séance.
     *
     * La relation est plusieurs-à-plusieurs des deux côtés : une séance couvre
     * souvent plusieurs leçons, et une leçon s'étale parfois sur plusieurs
     * séances. Marquer la leçon « faite » par un simple booléen perdrait cette
     * seconde information, et avec elle la date réelle du traitement.
     */
    public function up(): void
    {
        Schema::create('lecon_seance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progression_item_id')->constrained('progression_items')->cascadeOnDelete();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['progression_item_id', 'seance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecon_seance');
    }
};
