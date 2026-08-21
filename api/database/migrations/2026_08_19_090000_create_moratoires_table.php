<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moratoire sur la scolarité : le chef d'établissement accorde à une
     * famille un délai avant qu'elle ne soit relancée comme insolvable. La
     * date de délivrance trace la décision, la date d'expiration est la seule
     * information réellement consommée ailleurs (liste des insolvables).
     */
    public function up(): void
    {
        Schema::create('moratoires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->date('date_delivrance');
            $table->date('date_expiration');
            $table->string('motif')->nullable();
            $table->foreignId('accorde_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['eleve_id', 'date_expiration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moratoires');
    }
};
