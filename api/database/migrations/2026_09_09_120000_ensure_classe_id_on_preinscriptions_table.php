<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('preinscriptions', 'classe_id')) {
            Schema::table('preinscriptions', function (Blueprint $table) {
                $table->foreignId('classe_id')
                    ->nullable()
                    ->after('donnees_tuteurs')
                    ->constrained('classes')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('preinscriptions', 'classe_id')) {
            Schema::table('preinscriptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('classe_id');
            });
        }
    }
};
