<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sous_systemes', function (Blueprint $table) {
            $table->dropColumn('ordre');
        });
    }

    public function down(): void
    {
        Schema::table('sous_systemes', function (Blueprint $table) {
            $table->unsignedInteger('ordre')->default(0);
        });
    }
};
