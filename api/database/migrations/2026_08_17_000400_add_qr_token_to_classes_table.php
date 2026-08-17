<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Jeton affiché en QR code au mur de la salle : scanné par l'enseignant,
     * il ouvre directement le cours prévu à cette heure pour cette classe.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique()->after('capacite');
        });

        // Les classes existantes n'ont pas encore de jeton : leur QR code ne
        // s'affiche qu'une fois qu'elles en portent un.
        foreach (DB::table('classes')->whereNull('qr_token')->pluck('id') as $id) {
            DB::table('classes')->where('id', $id)->update(['qr_token' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
