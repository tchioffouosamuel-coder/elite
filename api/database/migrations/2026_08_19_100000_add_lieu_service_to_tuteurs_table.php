<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->string('lieu_service')->nullable()->after('profession');
        });
    }

    public function down(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->dropColumn('lieu_service');
        });
    }
};
