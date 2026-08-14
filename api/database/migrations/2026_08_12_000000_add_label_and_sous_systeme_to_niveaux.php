<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name_en'); // CE1, CLASS 1, etc.
            $table->foreignId('sous_system_id')->nullable()->after('label')->constrained('sous_systemes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['sous_system_id']);
            $table->dropColumn(['label', 'sous_system_id']);
        });
    }
};
