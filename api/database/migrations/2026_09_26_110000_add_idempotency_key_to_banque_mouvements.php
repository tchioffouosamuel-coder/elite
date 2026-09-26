<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banque_mouvements', function (Blueprint $table) {
            $table->string('cle_idempotence', 100)->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('banque_mouvements', function (Blueprint $table) {
            $table->dropUnique(['cle_idempotence']);
            $table->dropColumn('cle_idempotence');
        });
    }
};
