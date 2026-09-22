<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $banques = DB::table('banques')->orderBy('id')->get(['id', 'nom']);
        $canoniques = [];

        foreach ($banques as $banque) {
            $cle = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $banque->nom)));

            if (! isset($canoniques[$cle])) {
                $canoniques[$cle] = $banque->id;

                continue;
            }

            DB::table('personnels')
                ->where('banque_id', $banque->id)
                ->update(['banque_id' => $canoniques[$cle]]);
            DB::table('banques')->where('id', $banque->id)->delete();
        }

        Schema::table('banques', function (Blueprint $table) {
            $table->dropUnique('banques_school_id_nom_unique');
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
            $table->unique('nom');
        });
    }

    public function down(): void
    {
        Schema::table('banques', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->dropUnique('banques_nom_unique');
            $table->unique(['school_id', 'nom']);
        });
    }
};