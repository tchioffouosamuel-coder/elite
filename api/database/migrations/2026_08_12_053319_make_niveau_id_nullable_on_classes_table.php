<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une classe peut désormais exister sans niveau — l'import en masse
     * (nom, sigle, capacité) ne l'impose plus en amont, il s'affecte après
     * coup classe par classe. La suppression d'un niveau vide donc la
     * référence plutôt que d'emporter la classe (et ses élèves) avec elle.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['niveau_id']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('niveau_id')->nullable()->change();
            $table->foreign('niveau_id')->references('id')->on('niveaux')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['niveau_id']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('niveau_id')->nullable(false)->change();
            $table->foreign('niveau_id')->references('id')->on('niveaux')->cascadeOnDelete();
        });
    }
};
