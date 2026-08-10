<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('surveillant_general_id')->nullable()->after('professeur_principal_id')
                ->constrained('personnels')->nullOnDelete();
            $table->foreignId('censeur_id')->nullable()->after('surveillant_general_id')
                ->constrained('personnels')->nullOnDelete();
            $table->foreignId('conseiller_orientation_id')->nullable()->after('censeur_id')
                ->constrained('personnels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['surveillant_general_id']);
            $table->dropForeign(['censeur_id']);
            $table->dropForeign(['conseiller_orientation_id']);
            $table->dropColumn(['surveillant_general_id', 'censeur_id', 'conseiller_orientation_id']);
        });
    }
};
