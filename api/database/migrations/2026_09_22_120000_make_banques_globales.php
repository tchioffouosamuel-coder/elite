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
            // La clé étrangère d'abord : MySQL s'appuie sur l'index unique
            // (school_id, nom) pour la contrainte sur school_id et refuse de le
            // supprimer tant qu'elle existe (erreur 1553).
            $table->dropForeign(['school_id']);
            $table->dropUnique('banques_school_id_nom_unique');
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
