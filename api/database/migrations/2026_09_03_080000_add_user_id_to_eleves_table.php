<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Portail élève : un élève peut désormais avoir son propre compte de
     * connexion, symétrique à `tuteurs.user_id` pour le portail parent —
     * cf. `App\Services\CompteEleveService`.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
