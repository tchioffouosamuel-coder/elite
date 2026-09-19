<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File d'attente des fichiers (photos élève/personnel, justificatifs…)
     * référencés par une ligne tout juste appliquée par {@see \App\Console\Commands\SyncPull},
     * en attendant leur téléchargement effectif — voir
     * {@see \App\Console\Commands\SyncFichiers}.
     *
     * Sortir ce téléchargement de `sync:pull` évite qu'un premier clonage sur
     * un grand établissement (plusieurs milliers de photos) ne bloque
     * plusieurs minutes l'accès à l'application pour un aléa qui n'affecte
     * jamais la validité des données elles-mêmes (cf. commentaire de
     * `SyncPull::telechargerFichiers()` avant cette migration).
     */
    public function up(): void
    {
        Schema::create('sync_fichiers_en_attente', function (Blueprint $table) {
            $table->id();
            // Chemin relatif (valeur de la colonne `*_path`), unique : une
            // même photo référencée par plusieurs lignes synchronisées coup
            // sur coup (rare mais possible) ne doit être mise en file qu'une
            // fois.
            $table->string('chemin')->unique();
            $table->string('serveur_url');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_fichiers_en_attente');
    }
};
