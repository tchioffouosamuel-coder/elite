<?php

namespace App\Services;

/**
 * Catalogue des préférences configurables par établissement — porte les
 * réglages retrouvés dans _smapp (table `settings`, écran `preferences.php`)
 * dont plusieurs n'étaient en réalité jamais lus par le reste du code
 * (ex : encouragement/felicitations, avertissement/blame conduite). Ici
 * chaque clé est effectivement branchée : cf. MoyenneService::mentionTravail()
 * et ::mentionConduite().
 */
class SettingsCatalog
{
    /**
     * @return array<int, array{key:string, groupe:string, type:string, options?:array, default: string|int|float, label_fr:string, label_en:string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'num_sequences', 'groupe' => 'evaluations', 'type' => 'select',
                'options' => [2, 3], 'default' => 2,
                'label_fr' => 'Nombre de séquences par trimestre', 'label_en' => 'Sequences per term',
            ],
            [
                'key' => 'empty_cancel', 'groupe' => 'evaluations', 'type' => 'select',
                'options' => ['cancel', 'zero'], 'default' => 'cancel',
                'label_fr' => 'Note de séquence non saisie', 'label_en' => 'Missing sequence grade',
            ],
            [
                'key' => 'min_coef_per', 'groupe' => 'evaluations', 'type' => 'number',
                'default' => 50,
                'label_fr' => 'Seuil de remplissage des notes considéré "à jour" (%)',
                'label_en' => 'Grade-filling threshold considered "up to date" (%)',
            ],
            [
                'key' => 'honour_roll', 'groupe' => 'palmares', 'type' => 'number',
                'default' => 14,
                'label_fr' => 'Palmarès — moyenne minimale', 'label_en' => 'Honor roll — minimum average',
            ],
            [
                'key' => 'honour_attendance_max', 'groupe' => 'palmares', 'type' => 'number',
                'default' => 20,
                'label_fr' => 'Palmarès — heures non justifiées maximum', 'label_en' => 'Honor roll — max unexcused hours',
            ],
            [
                'key' => 'felicitations_min', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 16,
                'label_fr' => 'Mention "Félicitations" — moyenne minimale',
                'label_en' => '"With honors" mention — minimum average',
            ],
            [
                'key' => 'encouragements_min', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 14,
                'label_fr' => 'Mention "Encouragements" — moyenne minimale',
                'label_en' => '"Encouraged" mention — minimum average',
            ],
            [
                'key' => 'avertissement_travail_min', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 8,
                'label_fr' => 'Avertissement travail — moyenne minimale',
                'label_en' => 'Academic warning — minimum average',
            ],
            [
                'key' => 'avertissement_travail_max', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 10,
                'label_fr' => 'Avertissement travail — moyenne maximale',
                'label_en' => 'Academic warning — maximum average',
            ],
            [
                'key' => 'blame_travail_max', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 8,
                'label_fr' => 'Blâme travail — en dessous de cette moyenne',
                'label_en' => 'Academic reprimand — below this average',
            ],
            [
                'key' => 'avertissement_conduite_min', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 10,
                'label_fr' => 'Avertissement conduite — heures non justifiées minimales',
                'label_en' => 'Conduct warning — minimum unexcused hours',
            ],
            [
                'key' => 'avertissement_conduite_max', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 20,
                'label_fr' => 'Avertissement conduite — heures non justifiées maximales',
                'label_en' => 'Conduct warning — maximum unexcused hours',
            ],
            [
                'key' => 'blame_conduite_min', 'groupe' => 'mentions', 'type' => 'number',
                'default' => 20,
                'label_fr' => 'Blâme conduite — à partir de ce nombre d\'heures',
                'label_en' => 'Conduct reprimand — from this many hours',
            ],
            [
                // _smapp imprimait « CR 1357 » en dur sur chaque photo d'examen :
                // le code du centre est propre à l'établissement, il se règle ici.
                'key' => 'centre_examen', 'groupe' => 'examens', 'type' => 'text',
                'default' => '',
                'label_fr' => 'Code du centre d\'examen (imprimé sur les photos DECC/OBC)',
                'label_en' => 'Examination centre code (printed on DECC/OBC photos)',
            ],
        ];
    }

    public static function default(string $key): string|int|float|null
    {
        foreach (self::definitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition['default'];
            }
        }

        return null;
    }
}
