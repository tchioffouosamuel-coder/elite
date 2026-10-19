<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numérotation des reçus d'encaissement
    |--------------------------------------------------------------------------
    |
    | Une série par école, repartant à 1 à chaque année scolaire. Le numéro
    | remis à la famille se lit « RC-EBT-0042 » : préfixe, code de
    | l'établissement, puis le rang dans la série.
    |
    | Le code de l'école vient de sa fiche ; il distingue deux reçus portant le
    | même rang dans deux écoles du complexe, ce qu'un numéro seul ne ferait
    | pas — et le registre du complexe deviendrait ambigu au moment de
    | rapprocher les caisses.
    |
    */

    'prefixe' => env('RECU_PREFIXE', 'RC'),

    'longueur_numero' => (int) env('RECU_LONGUEUR_NUMERO', 4),

    /*
    |--------------------------------------------------------------------------
    | Cache des rendus PDF
    |--------------------------------------------------------------------------
    |
    | Composer un ticket coûte quelques centaines de millisecondes à mPDF ;
    | le résultat est mémorisé sur disque (cf. App\Support\Pdf\CacheRendus)
    | pour que la réimpression au guichet soit immédiate.
    |
    | `cache_retention_jours` : délai, depuis le dernier accès, au-delà duquel
    | `pdf:purger-cache` supprime une entrée. 0 désactive la purge.
    |
    */

    'cache' => (bool) env('RECU_CACHE', true),

    'cache_retention_jours' => (int) env('RECU_CACHE_RETENTION_JOURS', 30),

];
