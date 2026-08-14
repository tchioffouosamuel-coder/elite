<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropColumn(['label']);
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->string('label')->nullable()->after('code');
        });
    }
};
