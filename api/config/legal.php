<?php

/*
|--------------------------------------------------------------------------
| Mentions légales / politique de confidentialité
|--------------------------------------------------------------------------
|
| Valeurs affichées sur la page publique /politique-de-confidentialite,
| dont l'URL est déclarée dans la Google Play Console. Renseigner ces
| variables dans le .env de production avant publication.
|
*/

return [
    'app_name' => env('LEGAL_APP_NAME', 'Elites'),
    'editeur' => env('LEGAL_EDITEUR', 'Elites School'),
    'email' => env('LEGAL_EMAIL', 'contact@elites-school.com'),
    'telephone' => env('LEGAL_TELEPHONE', '+237 6 89 90 99 08'),
    'adresse' => env('LEGAL_ADRESSE', 'Yaoundé, Cameroun'),
    'maj' => env('LEGAL_MAJ', '11 octobre 2026'),
];
