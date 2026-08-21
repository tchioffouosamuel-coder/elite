<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('pere_nom_complet')->nullable()->after('date_fin');
            $table->enum('pere_statut', ['vivant', 'decede'])->nullable()->after('pere_nom_complet');
            $table->string('pere_telephone')->nullable()->after('pere_statut');
            $table->string('mere_nom_complet')->nullable()->after('pere_telephone');
            $table->enum('mere_statut', ['vivant', 'decede'])->nullable()->after('mere_nom_complet');
            $table->string('mere_telephone')->nullable()->after('mere_statut');
            $table->json('enfants')->nullable()->after('mere_telephone');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn([
                'pere_nom_complet',
                'pere_statut',
                'pere_telephone',
                'mere_nom_complet',
                'mere_statut',
                'mere_telephone',
                'enfants',
            ]);
        });
    }
};
