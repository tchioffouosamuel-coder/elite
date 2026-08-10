<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('complexe_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // Pilote le modèle de fonctionnement : au secondaire un enseignant par
            // matière avec départements ; au primaire/maternelle un enseignant par
            // classe avec des animateurs de niveau.
            $table->enum('type', ['maternelle', 'primaire', 'secondaire'])->default('secondaire')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['complexe_id']);
            $table->dropColumn(['complexe_id', 'type']);
        });
    }
};
