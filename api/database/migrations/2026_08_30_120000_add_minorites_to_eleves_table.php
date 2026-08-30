<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statut minoritaire de l'élève — jusqu'ici seul `deplace_interne`
     * existait ; le rapport de rentrée MINEDUB distingue aussi les élèves
     * Bororo et Baka dans son tableau des effectifs par minorité.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->enum('bororo', ['Oui', 'Non'])->nullable()->after('deplace_interne');
            $table->enum('baka', ['Oui', 'Non'])->nullable()->after('bororo');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['bororo', 'baka']);
        });
    }
};
