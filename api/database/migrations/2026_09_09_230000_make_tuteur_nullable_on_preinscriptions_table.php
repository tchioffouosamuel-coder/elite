<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->foreignId('tuteur_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->foreignId('tuteur_id')->nullable(false)->change();
        });
    }
};
