<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('titre');
            $table->text('contenu');
            $table->foreignId('publie_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->dateTime('publiee_le');
            $table->timestamps();

            $table->index(['school_id', 'publiee_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
