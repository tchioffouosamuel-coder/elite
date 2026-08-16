<?php

/*
|--------------------------------------------------------------------------
| Barème de paie — Cameroun
|--------------------------------------------------------------------------
|
| Taux et tranches en vigueur, tous surchargeables par l'environnement : un
| barème change par voie de loi de finances, et une valeur figée dans le code
| obligerait à livrer une version de l'application pour la suivre.
|
| Les taux s'expriment en pourcentage (4.2 = 4,2 %) et les montants en francs
| CFA. Les tranches sont ordonnées par plafond croissant ; la dernière, sans
| plafond, s'applique au-delà.
|
| ATTENTION : ces valeurs reproduisent le barème publié, mais la paie engage
| l'établissement devant la CNPS et le fisc. À faire confirmer par votre
| comptable avant le premier arrêté de paie — en particulier le taux
| d'accident du travail, qui dépend du groupe de risque attribué à l'école.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Assiette
    |--------------------------------------------------------------------------
    |
    | Les indemnités de transport et de communication sont exonérées dans la
    | limite d'un plafond mensuel : elles remboursent un frais, elles ne
    | rémunèrent pas un travail. C'est ce qui explique l'écart entre le brut
    | et la base de calcul de vos bulletins actuels.
    |
    */

    'exonerations' => [
        'transport' => (int) env('PAIE_EXO_TRANSPORT', 2500),
        'communication' => (int) env('PAIE_EXO_COMMUNICATION', 2500),
    ],

    /*
    |--------------------------------------------------------------------------
    | CNPS
    |--------------------------------------------------------------------------
    |
    | Cotisations plafonnées : au-delà du plafond mensuel, l'assiette n'augmente
    | plus. Les prestations familiales et l'accident du travail sont
    | exclusivement patronales.
    |
    */

    'cnps' => [
        'plafond_mensuel' => (int) env('PAIE_CNPS_PLAFOND', 750000),
        'pension_salarie' => (float) env('PAIE_CNPS_PENSION_SALARIE', 4.2),
        'pension_employeur' => (float) env('PAIE_CNPS_PENSION_EMPLOYEUR', 4.2),
        'prestations_familiales' => (float) env('PAIE_CNPS_PRESTATIONS_FAMILIALES', 7.0),
        // 1,75 %, 2,5 % ou 5 % selon le groupe de risque de l'établissement.
        'accidents_travail' => (float) env('PAIE_CNPS_ACCIDENTS_TRAVAIL', 1.75),
    ],

    /*
    |--------------------------------------------------------------------------
    | Crédit Foncier du Cameroun et Fonds National de l'Emploi
    |--------------------------------------------------------------------------
    */

    'cfc' => [
        'salarie' => (float) env('PAIE_CFC_SALARIE', 1.0),
        'employeur' => (float) env('PAIE_CFC_EMPLOYEUR', 1.5),
    ],

    'fne' => [
        // Exclusivement patronal.
        'employeur' => (float) env('PAIE_FNE_EMPLOYEUR', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Impôt sur le revenu des personnes physiques
    |--------------------------------------------------------------------------
    |
    | Barème progressif sur le revenu net imposable **annuel** :
    |
    |   RNI = (base taxable annuelle × 70 %) − pension CNPS annuelle − 500 000
    |
    | L'abattement de 30 % couvre les frais professionnels, celui de 500 000
    | francs est forfaitaire. L'impôt est calculé sur l'année puis ramené au
    | mois : appliquer les tranches à un montant mensuel fausserait le résultat
    | aux abords des seuils.
    |
    | Les centimes additionnels communaux s'ajoutent en pourcentage de l'impôt.
    |
    */

    'irpp' => [
        'taux_frais_professionnels' => (float) env('PAIE_IRPP_FRAIS_PROFESSIONNELS', 30.0),
        'abattement_annuel' => (int) env('PAIE_IRPP_ABATTEMENT_ANNUEL', 500000),
        'centimes_additionnels' => (float) env('PAIE_IRPP_CAC', 10.0),

        // [plafond annuel du revenu net imposable, taux]. `null` = au-delà.
        'tranches' => [
            [2000000, (float) env('PAIE_IRPP_TAUX_1', 10.0)],
            [3000000, (float) env('PAIE_IRPP_TAUX_2', 15.0)],
            [5000000, (float) env('PAIE_IRPP_TAUX_3', 25.0)],
            [null, (float) env('PAIE_IRPP_TAUX_4', 35.0)],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxe de développement local
    |--------------------------------------------------------------------------
    |
    | Montant forfaitaire mensuel par tranche de salaire de base, et non un
    | pourcentage : vos bulletins actuels appliquent un taux de 0,38 % qui en
    | est une approximation.
    |
    | [plafond mensuel du salaire de base, montant dû]. `null` = au-delà.
    |
    */

    'tdl' => [
        'tranches' => [
            [62000, 0],
            [75000, 250],
            [100000, 500],
            [125000, 750],
            [150000, 1000],
            [200000, 1250],
            [250000, 1500],
            [300000, 2000],
            [500000, 2250],
            [null, 2500],
        ],
    ],

];
