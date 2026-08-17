<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Numéro de permis de conduire — pertinent uniquement pour les chauffeurs. */
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('numero_permis')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('numero_permis');
        });
    }
};
