<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $eleves = DB::table('eleves')
            ->select(['id', 'nom_complet'])
            ->get()
            ->sortBy(fn(object $eleve) => [
                Str::ascii(mb_strtolower(trim($eleve->nom_complet))),
                $eleve->id,
            ])
            ->values();

        foreach ($eleves as $eleve) {
            DB::table('eleves')
                ->where('id', $eleve->id)
                ->update(['matricule' => "__normalisation_25_{$eleve->id}"]);
        }

        foreach ($eleves as $index => $eleve) {
            DB::table('eleves')
                ->where('id', $eleve->id)
                ->update([
                    'matricule' => '25ELITES-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                ]);
        }
    }

    public function down(): void {}
};
