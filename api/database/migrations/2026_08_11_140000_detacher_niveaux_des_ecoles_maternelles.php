<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La maternelle ne s'organise pas en degrés d'enseignement : ses sections
     * — petite, moyenne, grande — sont directement des classes, sans animateur
     * de niveau au-dessus d'elles.
     *
     * Les établissements déjà installés portent les niveaux créés avant cette
     * règle : les détacher ici évite qu'elle ne vaille que pour les nouvelles
     * installations. Les classes, elles, sont conservées — seul leur
     * rattachement disparaît.
     */
    public function up(): void
    {
        $maternelles = DB::table('schools')->where('type', 'maternelle')->pluck('id');

        if ($maternelles->isEmpty()) {
            return;
        }

        DB::table('classes')
            ->whereIn('school_id', $maternelles)
            ->update(['niveau_scolaire_id' => null]);

        DB::table('niveau_scolaires')->whereIn('school_id', $maternelles)->delete();
    }

    /**
     * Irréversible : les niveaux supprimés ne peuvent pas être devinés, et les
     * recréer vides rendrait l'ancien état sans en rétablir le contenu.
     */
    public function down(): void {}
};
