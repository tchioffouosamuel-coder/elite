<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Code de l'établissement sur cartescolaire.cm — sert à interroger
            // MatriculeNationalService pour retrouver le matricule national des
            // élèves. Uniquement pertinent au secondaire (cf. School::estSecondaire()).
            $table->string('national_school_code')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('national_school_code');
        });
    }
};
