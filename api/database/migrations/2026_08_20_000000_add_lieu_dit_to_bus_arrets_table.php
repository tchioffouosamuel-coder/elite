<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->string('lieu_dit', 150)->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->dropColumn('lieu_dit');
        });
    }
};
