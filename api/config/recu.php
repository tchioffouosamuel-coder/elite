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

];
