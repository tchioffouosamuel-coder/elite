<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `mentions_legales` devient un champ WYSIWYG : le HTML (mise en forme,
        // paragraphes) dépasse largement les 255 caractères du VARCHAR d'origine.
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('value')->change();
        });
    }
};
