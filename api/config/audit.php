<?php

/*
 * Journal d'audit (cf. App\Http\Middleware\JournaliserAudit).
 */
return [

    'actif' => env('AUDIT_ACTIF', true),

    /*
     * Durée de conservation, en jours, au-delà de laquelle `audit:purger`
     * supprime les lignes. 0 = conservation illimitée.
     */
    'retention_jours' => (int) env('AUDIT_RETENTION_JOURS', 365),

    /*
     * Routes jamais journalisées : appels de fond déclenchés par
     * l'application elle-même (minuteries, synchronisation automatique),
     * jamais par un geste de l'utilisateur. Les journaliser noierait le
     * journal sous des milliers de lignes sans valeur.
     */
    'routes_exclues' => [
        'api.v1.sync.pull',
        'api.v1.sync.comptage',
        'api.v1.notifications.non-lues',
        'api.v1.desktop.statut-sync',
        'api.v1.historique.index',
    ],

    /*
     * Rafraîchissement automatique de la console d'audit : ignoré
     * seulement quand le paramètre `actualisation_auto=1` est présent ET
     * que la route figure ici — l'ouverture manuelle de la console, ou son
     * export, restent journalisés.
     */
    'routes_actualisation_auto' => [
        'api.v1.audit.index',
        'api.v1.audit.stats',
    ],

    /*
     * Champs masqués dans les données envoyées, insensible à la casse :
     * `contient` sur une partie du nom, `exacts` sur le nom entier (mots
     * trop courts pour une correspondance partielle — « pin » masquerait
     * aussi « pinceau »).
     */
    'champs_sensibles' => [
        'contient' => ['password', 'mot_de_passe', 'token', 'secret', 'authorization'],
        'exacts' => ['otp', 'pin', 'code_pin'],
    ],

    /*
     * Modèles dont les écritures ne sont pas détaillées dans `changements`
     * (tables techniques réécrites à chaque requête).
     */
    'modeles_exclus' => [
        App\Models\AuditLog::class,
        App\Models\ActionAnnulable::class,
        App\Models\ActivityLog::class,
        App\Models\SyncOutbox::class,
        App\Models\SyncTombstone::class,
        App\Models\SyncFichierEnAttente::class,
        App\Models\IdempotencyKey::class,
        App\Models\DeviceToken::class,
    ],

    /* Plafonds de taille, pour qu'un import massif ne fasse pas exploser une ligne. */
    'max_changements' => 200,
    'max_longueur_texte' => 500,
];
