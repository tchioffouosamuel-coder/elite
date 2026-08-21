<?php

/*
|--------------------------------------------------------------------------
| Comptabilité
|--------------------------------------------------------------------------
|
| Réglages du circuit financier propres à l'établissement. Les tarifs des
| prélèvements par élève (SEDUC, Fenasco, assurance) ne sont pas ici : ils
| vivent sur le compte lui-même, où le comptable peut les corriger sans
| livraison.
|
*/

return [

    /*
     * Durée d'étalement d'une construction, en années. Le classeur ne dotait
     * rien : 202 millions de bâtiments sur onze exercices, tous passés en
     * charge l'année même, et un compte 699 resté à zéro. Vingt ans est la
     * durée usuelle d'un bâtiment scolaire ; elle se règle bien par bien.
     */
    'duree_amortissement_batiments' => (int) env('COMPTA_DUREE_AMORTISSEMENT_BATIMENTS', 20),

];
