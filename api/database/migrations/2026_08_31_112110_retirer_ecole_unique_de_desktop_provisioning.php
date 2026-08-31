<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un poste peut désormais répliquer plusieurs écoles (super admin d'un
     * complexe) : l'école unique et son curseur de synchronisation migrent
     * vers `desktop_provisioning_ecoles`, une ligne par école. `dernier_push_le`
     * reste ici : l'outbox est commune à toutes les écoles du poste, elle ne
     * se rejoue pas par école.
     */
    public function up(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'curseur_sync', 'dernier_pull_le']);
        });
    }

    public function down(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('user_id')->constrained('schools')->nullOnDelete();
            $table->string('curseur_sync')->nullable();
            $table->timestamp('dernier_pull_le')->nullable();
        });
    }
};
