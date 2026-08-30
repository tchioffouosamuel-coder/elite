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
                'key' => 'num_sequences',
                'groupe' => 'evaluations',
                'type' => 'select',
                'options' => [2, 3],
                'default' => 2,
                'label_fr' => 'Nombre de séquences par trimestre',
                'label_en' => 'Sequences per term',
            ],
            [
                'key' => 'empty_cancel',
                'groupe' => 'evaluations',
                'type' => 'select',
                'options' => ['cancel', 'zero'],
                'default' => 'cancel',
                'label_fr' => 'Note de séquence non saisie',
                'label_en' => 'Missing sequence grade',
            ],
            [
                'key' => 'min_coef_per',
                'groupe' => 'evaluations',
                'type' => 'number',
                'default' => 50,
                'label_fr' => 'Seuil de remplissage des notes considéré "à jour" (%)',
                'label_en' => 'Grade-filling threshold considered "up to date" (%)',
            ],
            [
                'key' => 'honour_roll',
                'groupe' => 'palmares',
                'type' => 'number',
                'default' => 14,
                'label_fr' => 'Palmarès — moyenne minimale',
                'label_en' => 'Honor roll — minimum average',
            ],
            [
                'key' => 'honour_attendance_max',
                'groupe' => 'palmares',
                'type' => 'number',
                'default' => 20,
                'label_fr' => 'Palmarès — heures non justifiées maximum',
                'label_en' => 'Honor roll — max unexcused hours',
            ],
            [
                'key' => 'felicitations_min',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 16,
                'label_fr' => 'Mention "Félicitations" — moyenne minimale',
                'label_en' => '"With honors" mention — minimum average',
            ],
            [
                'key' => 'encouragements_min',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 14,
                'label_fr' => 'Mention "Encouragements" — moyenne minimale',
                'label_en' => '"Encouraged" mention — minimum average',
            ],
            [
                'key' => 'avertissement_travail_min',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 8,
                'label_fr' => 'Avertissement travail — moyenne minimale',
                'label_en' => 'Academic warning — minimum average',
            ],
            [
                'key' => 'avertissement_travail_max',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 10,
                'label_fr' => 'Avertissement travail — moyenne maximale',
                'label_en' => 'Academic warning — maximum average',
            ],
            [
                'key' => 'blame_travail_max',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 8,
                'label_fr' => 'Blâme travail — en dessous de cette moyenne',
                'label_en' => 'Academic reprimand — below this average',
            ],
            [
                'key' => 'avertissement_conduite_min',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 10,
                'label_fr' => 'Avertissement conduite — heures non justifiées minimales',
                'label_en' => 'Conduct warning — minimum unexcused hours',
            ],
            [
                'key' => 'avertissement_conduite_max',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 20,
                'label_fr' => 'Avertissement conduite — heures non justifiées maximales',
                'label_en' => 'Conduct warning — maximum unexcused hours',
            ],
            [
                'key' => 'blame_conduite_min',
                'groupe' => 'mentions',
                'type' => 'number',
                'default' => 20,
                'label_fr' => 'Blâme conduite — à partir de ce nombre d\'heures',
                'label_en' => 'Conduct reprimand — from this many hours',
            ],
            [
                // _smapp imprimait « CR 1357 » en dur sur chaque photo d'examen :
                // le code du centre est propre à l'établissement, il se règle ici.
                'key' => 'centre_examen',
                'groupe' => 'examens',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Code du centre d\'examen (imprimé sur les photos DECC/OBC)',
                'label_en' => 'Examination centre code (printed on DECC/OBC photos)',
            ],
            // Primaire et maternelle : le passage en classe supérieure se décide
            // sur la moyenne annuelle (archange, decision.php — seuil par défaut 10).
            [
                'key' => 'passage_moyenne_min',
                'groupe' => 'passage',
                'type' => 'number',
                'default' => 10,
                'label_fr' => 'Passage en classe supérieure — moyenne annuelle minimale',
                'label_en' => 'Promotion to next class — minimum annual average',
            ],
            // Mentions signataires et légales des documents officiels. Le
            // certificat de scolarité les imprime ; elles diffèrent d'une école
            // du complexe à l'autre (arrêté de création, comptes, immatriculation)
            // et ne peuvent donc pas être écrites en dur dans le générateur.
            [
                'key' => 'chef_etablissement',
                'groupe' => 'documents',
                'type' => 'text',
                'default' => '',
                'label_fr' => "Nom du chef d'établissement (signataire des documents)",
                'label_en' => 'Head of school (signs official documents)',
            ],
            [
                'key' => 'chef_etablissement_titre',
                'groupe' => 'documents',
                'type' => 'text',
                'default' => "Le Chef d'Établissement",
                'label_fr' => 'Titre du signataire',
                'label_en' => 'Signatory title',
            ],
            [
                'key' => 'mentions_legales',
                'groupe' => 'documents',
                'type' => 'richtext',
                'default' => '',
                'label_fr' => 'Mentions légales en pied de document (arrêté, comptes, immatriculation)',
                'label_en' => 'Legal notices in document footer (order, accounts, registration)',
            ],
            // Un élève dont le retard dépasse ce pourcentage de sa scolarité
            // apparaît sur la liste des insolvables — indépendant du statut
            // binaire impayé/partiel déjà utilisé à la caisse.
            [
                'key' => 'seuil_insolvabilite',
                'groupe' => 'finance',
                'type' => 'number',
                'default' => 0,
                'label_fr' => "Seuil d'insolvabilité — pourcentage de la scolarité restant dû (%)",
                'label_en' => 'Insolvency threshold — percentage of tuition still due (%)',
            ],
            // Politiques d'établissement, affichées aux parents sur la carte
            // finance de leur enfant — pas de date par élève ou par classe, une
            // seule échéance pour toute l'école.
            // Tolérance après une échéance avant de compter le retard : une
            // famille qui règle le lendemain de la date ne doit pas basculer
            // sur la liste des insolvables entre-temps.
            [
                'key' => 'delai_grace_echeance',
                'groupe' => 'finance',
                'type' => 'number',
                'default' => 0,
                'label_fr' => "Délai de grâce après une échéance avant de compter le retard (jours)",
                'label_en' => 'Grace period after a due date before counting arrears (days)',
            ],
            [
                'key' => 'date_limite_paiement',
                'groupe' => 'finance',
                'type' => 'date',
                'default' => '',
                'label_fr' => 'Date limite de paiement de la scolarité',
                'label_en' => 'Tuition payment deadline',
            ],
            [
                'key' => 'date_exclusion_insolvables',
                'groupe' => 'finance',
                'type' => 'date',
                'default' => '',
                'label_fr' => 'Date d\'exclusion des élèves insolvables',
                'label_en' => 'Exclusion date for insolvent students',
            ],
            // Configurables ici plutôt que dans `.env` : un mot de passe par
            // défaut qui ne dépend plus d'une variable serveur inaccessible
            // sans le devops, et modifiable par le super admin lui-même dès
            // qu'il en a besoin — voir CompteAgentService/CompteParentService.
            [
                'key' => 'mot_de_passe_defaut',
                'groupe' => 'personnel',
                'type' => 'text',
                'default' => 'Elite@2026',
                'label_fr' => 'Mot de passe par défaut des nouveaux comptes (personnel et parents)',
                'label_en' => 'Default password for new accounts (staff and parents)',
            ],
            [
                'key' => 'domaine_email',
                'groupe' => 'personnel',
                'type' => 'text',
                'default' => 'elite.school',
                'label_fr' => "Domaine des adresses e-mail générées pour le personnel",
                'label_en' => 'Domain for generated staff email addresses',
            ],
            // Section I du rapport de rentrée MINEDUB — identité administrative
            // de l'établissement (arrondissement, textes d'ouverture, fondateur…),
            // absente du schéma `schools` qui ne porte que ce dont le reste de
            // l'application se sert (nom, contact, en-têtes).
            [
                'key' => 'arrondissement',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Arrondissement',
                'label_en' => 'District',
            ],
            [
                'key' => 'secteur',
                'groupe' => 'identite_etablissement',
                'type' => 'select',
                'options' => ['Privé', 'Public'],
                'default' => 'Privé',
                'label_fr' => 'Secteur',
                'label_en' => 'Sector',
            ],
            [
                'key' => 'cycle',
                'groupe' => 'identite_etablissement',
                'type' => 'select',
                'options' => ['Complet', 'Incomplet'],
                'default' => 'Complet',
                'label_fr' => 'Cycle',
                'label_en' => 'Cycle',
            ],
            [
                'key' => 'mode_fonctionnement',
                'groupe' => 'identite_etablissement',
                'type' => 'select',
                'options' => ['Plein temps', 'Mi-temps'],
                'default' => 'Plein temps',
                'label_fr' => 'Mode de fonctionnement',
                'label_en' => 'Operating mode',
            ],
            [
                'key' => 'annee_creation',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Année de création',
                'label_en' => 'Year founded',
            ],
            [
                'key' => 'annee_ouverture',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => "Année d'ouverture",
                'label_en' => 'Year opened',
            ],
            [
                'key' => 'numero_arrete_ouverture',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => "Arrêté/décision d'ouverture (n° et date)",
                'label_en' => 'Opening decree (no. and date)',
            ],
            [
                'key' => 'numero_autorisation_ouverture',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => "Autorisation d'ouverture (n° et date)",
                'label_en' => 'Opening authorization (no. and date)',
            ],
            [
                'key' => 'fondateur_nom',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Nom et prénom du fondateur',
                'label_en' => "Founder's name",
            ],
            [
                'key' => 'fondateur_contact',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Contact du fondateur',
                'label_en' => "Founder's contact",
            ],
            [
                'key' => 'directeur_nom',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Nom et prénom du directeur',
                'label_en' => "Director's name",
            ],
            [
                'key' => 'directeur_contact',
                'groupe' => 'identite_etablissement',
                'type' => 'text',
                'default' => '',
                'label_fr' => 'Contact du directeur',
                'label_en' => "Director's contact",
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
