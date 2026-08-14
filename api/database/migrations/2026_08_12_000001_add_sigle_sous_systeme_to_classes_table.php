<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('sigle')->nullable()->after('nom'); // Abbreviation like GS-A, CE1-B
            $table->foreignId('sous_systeme_id')->nullable()->after('sigle')->constrained('sous_systemes')->nullOnDelete();
            $table->string('niveau_classe')->nullable()->after('sous_systeme_id'); // Level name like "GRANDE SECTION"
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['sous_systeme_id']);
            $table->dropColumn(['sigle', 'sous_systeme_id', 'niveau_classe']);
        });
    }
};
