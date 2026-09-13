<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deux compléments à la preuve de présence de « Ma journée » :
     * - `code_salle` : version courte et saisissable à la main du QR code de
     *   la salle (`qr_token`, un UUID — imprononçable au téléphone), pour
     *   l'enseignant dont l'appareil ne peut pas scanner.
     * - `methode_validation_seance` sur le dossier de l'agent : la direction
     *   choisit, agent par agent, s'il doit scanner, saisir ce code, ou
     *   valider librement (sans preuve) — `qr` par défaut pour ne rien
     *   changer au comportement existant.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('code_salle', 8)->nullable()->unique()->after('qr_token');
        });

        foreach (DB::table('classes')->whereNull('code_salle')->pluck('id') as $id) {
            DB::table('classes')->where('id', $id)->update(['code_salle' => self::genererCode()]);
        }

        Schema::table('personnels', function (Blueprint $table) {
            $table->enum('methode_validation_seance', ['qr', 'code', 'libre'])->default('qr')->after('numero_compte');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('code_salle');
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('methode_validation_seance');
        });
    }

    /** Code à 6 chiffres, sans confusion possible avec un numéro de reçu ou un identifiant. */
    private static function genererCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (DB::table('classes')->where('code_salle', $code)->exists());

        return $code;
    }
};
