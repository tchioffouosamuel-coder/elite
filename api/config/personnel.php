<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comptes de connexion du personnel
    |--------------------------------------------------------------------------
    |
    | Chaque agent reçoit un accès dès sa création, y compris lors d'un import.
    | L'adresse est dérivée de son nom sur ce domaine ; elle sert d'identifiant
    | de connexion, pas de boîte aux lettres — les agents n'ont pas tous une
    | adresse professionnelle.
    |
    | Le mot de passe est commun à la première connexion : c'est ce que demande
    | la reprise d'un effectif entier d'un coup. Il doit être changé par chaque
    | agent, et l'établissement a tout intérêt à redéfinir cette valeur par
    | l'environnement plutôt que de garder celle livrée.
    |
    */

    'domaine_email' => env('PERSONNEL_DOMAINE_EMAIL', 'elite.school'),

    'mot_de_passe_defaut' => env('PERSONNEL_MOT_DE_PASSE_DEFAUT', 'Elite@2026'),

];
