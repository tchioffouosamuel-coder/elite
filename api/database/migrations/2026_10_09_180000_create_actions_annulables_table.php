<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions_annulables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('school_id');
            $table->string('route');
            $table->string('libelle');
            $table->string('etat', 20)->default('appliquee');
            $table->unsignedInteger('revision')->default(0);
            $table->json('contexte');
            $table->json('changements');
            $table->timestamps();
            $table->index(['user_id', 'school_id', 'etat', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions_annulables');
    }
};
