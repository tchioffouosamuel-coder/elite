<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('annee_scolaires')) {
            return;
        }

        Schema::create('annee_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('libelle');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('is_active')->default(false);
            $table->timestamp('archivee_le')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'libelle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annee_scolaires');
    }
};