<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Niveaux d'enseignement à l'intérieur d'un établissement (SIL, CP, CE1…
     * au primaire ; Petite/Moyenne/Grande section en maternelle).
     *
     * À ne pas confondre avec la table `niveaux`, qui désigne le type
     * d'établissement (maternelle / primaire / collège) au sein du complexe.
     *
     * Au primaire et en maternelle, le pilotage pédagogique se fait par niveau
     * — chaque niveau a un « animateur de niveau » — là où le secondaire
     * s'organise par département avec un chef de département.
     */
    public function up(): void
    {
        Schema::create('niveau_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);            // SIL, CP, CE1, PS, MS, GS…
            $table->string('libelle');             // Cours Préparatoire, Petite Section…
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->foreignId('animateur_personnel_id')->nullable()
                ->constrained('personnels')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_scolaires');
    }
};
