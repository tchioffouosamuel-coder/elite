<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('sous_system_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['school_id']);
            $table->dropColumn(['school_id']);
        });
    }
};
