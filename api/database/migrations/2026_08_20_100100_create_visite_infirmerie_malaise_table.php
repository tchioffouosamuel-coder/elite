<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visite_infirmerie_malaise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visite_infirmerie_id')->constrained('visites_infirmerie')->cascadeOnDelete();
            $table->foreignId('malaise_referentiel_id')->constrained('malaises_referentiel')->cascadeOnDelete();
            $table->unique(['visite_infirmerie_id', 'malaise_referentiel_id'], 'visite_malaise_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visite_infirmerie_malaise');
    }
};
