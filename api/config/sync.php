<?php

return [

    /*
     * Vrai uniquement sur une instance Laravel locale (client desktop
     * offline) : active l'enregistrement automatique des écritures dans
     * `sync_outbox` ({@see \App\Http\Middleware\EnregistrerDansOutboxLocale})
     * pour un rejeu ultérieur vers le serveur distant. Sur le serveur
     * distant lui-même, cette variable reste fausse — il n'a personne vers
     * qui pousser.
     */
    'local_replica' => env('SYNC_LOCAL_REPLICA', false),

];
