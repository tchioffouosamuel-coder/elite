<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la contrainte UNIQUE erronée sur code seul
        Schema::table('sous_systemes', function (Blueprint $table) {
            $table->dropUnique('sous_systemes_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sous_systemes', function (Blueprint $table) {
            $table->unique(['code']);
        });
    }
};
