<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un fichier (photo élève/personnel, justificatif…) restant à télécharger
 * depuis le serveur distant — voir {@see \App\Console\Commands\SyncPull}
 * (alimentation) et {@see \App\Console\Commands\SyncFichiers} (vidage).
 */
class SyncFichierEnAttente extends Model
{
    protected $table = 'sync_fichiers_en_attente';

    public $timestamps = false;

    protected $fillable = ['chemin', 'serveur_url', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
