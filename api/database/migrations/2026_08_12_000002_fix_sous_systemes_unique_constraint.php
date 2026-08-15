<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la contrainte UNIQUE erronée sur code seul. La table telle
        // qu'elle est créée aujourd'hui (2026_08_12_000000) ne porte plus que
        // l'unique composite : sur une base repartie de zéro — les tests, qui
        // tournent sur SQLite — l'index n'existe donc pas et le drop échouait.
        try {
            Schema::table('sous_systemes', function (Blueprint $table) {
                $table->dropUnique('sous_systemes_code_unique');
            });
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        Schema::table('sous_systemes', function (Blueprint $table) {
            $table->unique(['code']);
        });
    }
};
