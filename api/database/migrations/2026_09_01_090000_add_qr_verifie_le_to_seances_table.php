<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            // Horodatage du scan du QR de la salle qui a précédé l'appel — la
            // preuve que l'enseignant validait bien depuis la classe, pas
            // depuis chez lui. Reste `null` pour une validation par la
            // direction (dispensée du scan, cf. User::peutSaisirHorsTrimestreActif())
            // ou pour toute séance saisie avant l'introduction de ce contrôle.
            $table->timestamp('qr_verifie_le')->nullable()->after('appel_verrouille_le');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('qr_verifie_le');
        });
    }
};
