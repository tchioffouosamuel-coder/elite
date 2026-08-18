<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Envoi de SMS (confirmations de paiement, alertes bus…). `driver` vaut
     * `log` tant qu'aucun compte Twilio n'est configuré : les messages
     * s'écrivent alors dans les logs au lieu de partir réellement, ce qui
     * permet de développer et tester sans compte actif.
     */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    /*
     * Notifications push vers l'application mobile. `log` tant qu'aucun projet
     * Firebase n'est créé : les envois s'écrivent dans les logs, ce qui permet
     * de vérifier les déclencheurs sans identifiants.
     */
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
    ],

    'fcm' => [
        'projet' => env('FCM_PROJECT_ID'),
        // Chemin du JSON de compte de service Firebase, hors du dépôt.
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

];
