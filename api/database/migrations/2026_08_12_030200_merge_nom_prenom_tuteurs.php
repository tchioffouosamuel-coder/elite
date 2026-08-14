<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Même fusion que sur `eleves` (cf. 2026_08_12_030000), pour les tuteurs. */
    public function up(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->string('nom_complet')->nullable()->after('user_id');
        });

        DB::table('tuteurs')->select('id', 'nom', 'prenom')->orderBy('id')->get()->each(
            fn ($row) => DB::table('tuteurs')->where('id', $row->id)
                ->update(['nom_complet' => trim($row->prenom.' '.$row->nom)])
        );

        Schema::table('tuteurs', function (Blueprint $table) {
            $table->string('nom_complet')->nullable(false)->change();
            $table->dropColumn(['nom', 'prenom']);
        });
    }

    /** Retour arrière best-effort : voir 2026_08_12_030000_merge_nom_prenom_eleves. */
    public function down(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->string('nom')->nullable()->after('user_id');
            $table->string('prenom')->nullable()->after('nom');
        });

        DB::table('tuteurs')->select('id', 'nom_complet')->orderBy('id')->get()->each(function ($row) {
            $parties = explode(' ', trim($row->nom_complet), 2);
            DB::table('tuteurs')->where('id', $row->id)->update([
                'prenom' => $parties[0] ?? '',
                'nom' => $parties[1] ?? '',
            ]);
        });

        Schema::table('tuteurs', function (Blueprint $table) {
            $table->string('nom')->nullable(false)->change();
            $table->string('prenom')->nullable(false)->change();
            $table->dropColumn('nom_complet');
        });
    }
};
