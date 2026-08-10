<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->foreignId('head_personnel_id')->nullable()->after('nom')->constrained('personnels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_personnel_id');
        });
    }
};
