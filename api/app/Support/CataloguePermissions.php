<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Catalogue des privilèges de l'application, regroupés par module.
 *
 * Le catalogue vit dans le code et non en base : un privilège n'existe que
 * parce qu'une route l'exige, en créer un depuis l'interface ne protégerait
 * rien. Ce que le super administrateur gère, c'est leur **répartition** entre
 * les fonctions (cf. `fonction_permission`) — pas la liste elle-même.
 *
 * Les libellés servent à l'écran de gestion des permissions et aux messages
 * d'erreur : « Il vous manque le privilège “Modifier les élèves” » est plus
 * exploitable pour un chef d'établissement que « eleves.manage ».
 */
class CataloguePermissions
{
    /**
     * module => [libellé du module, [code => [libellé fr, libellé en]]].
     *
     * Une entrée ajoutée ici doit l'être en même temps que la route qui
     * l'exige. Les groupes de privilèges par fonction (cf.
     * {@see FonctionReferentiel::synchroniserPermissions()}) créent la ligne
     * manquante à la volée ; les rôles techniques (cf. `RolePermissionSeeder`)
     * ont eux besoin d'un nouveau `php artisan db:seed --class=RolePermissionSeeder`
     * pour recevoir un privilège tout juste ajouté ici.
     */
    private const MODULES = [
        'dashboard' => ['Tableau de bord', 'Dashboard', [
            'dashboard.view' => ['Consulter le tableau de bord', 'View the dashboard'],
        ]],
        'ecoles' => ['Établissement', 'School', [
            'ecoles.manage' => ["Administrer l'établissement (année scolaire, trimestres, paramètres)", 'Administer the school'],
        ]],
        'personnel' => ['Personnel', 'Staff', [
            'personnel.view' => ['Consulter le personnel', 'View staff'],
            'personnel.manage' => ['Gérer le personnel, les départements et les fonctions', 'Manage staff'],
        ]],
        'classes' => ['Classes', 'Classes', [
            'classes.view' => ['Consulter les classes', 'View classes'],
            'classes.manage' => ['Créer et modifier les classes', 'Manage classes'],
        ]],
        'niveaux' => ['Niveaux globaux', 'Global levels', [
            'niveaux.view' => ['Consulter les niveaux', 'View levels'],
            'niveaux.manage' => ['Gérer les niveaux', 'Manage levels'],
        ]],
        'eleves' => ['Élèves', 'Pupils', [
            'eleves.view' => ['Consulter les élèves', 'View pupils'],
            'eleves.manage' => ['Inscrire, modifier, transférer et importer des élèves', 'Manage pupils'],
        ]],
        'pedagogie' => ['Pédagogie', 'Teaching', [
            'pedagogie.view' => ['Consulter matières, affectations et progression', 'View teaching data'],
            'pedagogie.manage' => ['Gérer matières, affectations et progression', 'Manage teaching data'],
        ]],
        'notes' => ['Notes', 'Marks', [
            'notes.view' => ['Consulter les notes et les classements', 'View marks'],
            'notes.create' => ['Saisir et importer des notes', 'Enter marks'],
        ]],
        'appel' => ['Appel', 'Attendance', [
            'appel.manage' => ["Faire l'appel et déclarer les leçons traitées", 'Take attendance'],
        ]],
        'discipline' => ['Discipline', 'Discipline', [
            'discipline.view' => ['Consulter absences et sanctions', 'View discipline records'],
            'discipline.manage' => ['Enregistrer absences et sanctions', 'Manage discipline records'],
        ]],
        'infirmerie' => ['Infirmerie', 'Infirmary', [
            'infirmerie.view' => ["Consulter les visites à l'infirmerie", 'View infirmary visits'],
            'infirmerie.manage' => ["Enregistrer et modifier les visites à l'infirmerie", 'Manage infirmary visits'],
        ]],
        'bus' => ['Transport scolaire', 'School transport', [
            'bus.view' => ['Consulter véhicules, trajets et affectations', 'View vehicles, routes and assignments'],
            'bus.manage' => ['Gérer la flotte, les trajets et les affectations des élèves', 'Manage the fleet, routes and pupil assignments'],
        ]],
        'inventaire' => ['Inventaire', 'Inventory', [
            'inventaire.view' => ['Consulter l\'inventaire du matériel', 'View the equipment inventory'],
            'inventaire.manage' => ['Gérer les articles de l\'inventaire', 'Manage inventory items'],
        ]],
        /*
         * Distinct de `inventaire` (matériel consommable/vendable) : ce
         * module couvre le bâti et le mobilier fixe recensés au rapport de
         * rentrée MINEDUB — salles de classe, points d'eau, tables-bancs…
         */
        'infrastructures' => ['Infrastructures et mobilier', 'Infrastructure and furniture', [
            'infrastructures.view' => ['Consulter les infrastructures et le mobilier', 'View infrastructure and furniture'],
            'infrastructures.manage' => ['Gérer les infrastructures et le mobilier', 'Manage infrastructure and furniture'],
        ]],
        /*
         * Rubriques du rapport de rentrée MINEDUB qui ne rentrent dans aucun
         * autre module : visites d'autorités, activités pédagogiques/EPS/
         * FENASSCO, vente de denrées et blocs de texte libre (sécurité,
         * gouvernements d'enfants, doléances…).
         */
        'rapport_rentree' => ['Rapport de rentrée', 'Back-to-school report', [
            'rapport_rentree.view' => ['Consulter le rapport de rentrée', 'View the back-to-school report'],
            'rapport_rentree.manage' => ['Renseigner le rapport de rentrée', 'Fill in the back-to-school report'],
        ]],
        /*
         * Rapport de fin de trimestre MINEDUB : blocs de texte libre
         * (introduction, observations, difficultés rencontrées, conclusion)
         * — le reste du contenu (effectifs, fréquentation, pédagogie) vient
         * déjà des modules Élèves/Discipline/Progression/Résultats.
         */
        'rapport_trimestre' => ['Rapport de fin de trimestre', 'End-of-term report', [
            'rapport_trimestre.view' => ['Consulter le rapport de fin de trimestre', 'View the end-of-term report'],
            'rapport_trimestre.manage' => ['Renseigner le rapport de fin de trimestre', 'Fill in the end-of-term report'],
        ]],
        /*
         * Le comptoir se sépare de l'inventaire : le vendeur écoule le stock
         * sans avoir à modifier la fiche des articles, et l'économe tient
         * l'inventaire sans forcément tenir la caisse de la boutique. Vendre
         * est isolé d'« administrer » pour la même raison qu'encaisser l'est
         * de paramétrer les tarifs, côté finances.
         */
        'point_de_vente' => ['Point de vente', 'Point of sale', [
            'point_de_vente.view' => ['Consulter les ventes et les entrées de stock', 'View sales and stock entries'],
            'point_de_vente.vendre' => ['Vendre au comptoir et éditer les factures', 'Sell at the counter and issue invoices'],
            'point_de_vente.manage' => ["Enregistrer les entrées de stock et annuler une vente", 'Record stock entries and cancel a sale'],
        ]],
        'bulletins' => ['Bulletins et statistiques', 'Reports and statistics', [
            'bulletins.view' => ['Consulter bulletins, palmarès et statistiques', 'View reports'],
            'bulletins.publish' => ['Publier les bulletins', 'Publish reports'],
        ]],
        'emploi_du_temps' => ['Emploi du temps', 'Timetable', [
            'emploi_du_temps.view' => ["Consulter l'emploi du temps et les séances", 'View the timetable'],
            'emploi_du_temps.manage' => ["Gérer l'emploi du temps et les séances", 'Manage the timetable'],
        ]],
        /*
         * Les finances se découpent plus finement que le reste : l'économe
         * encaisse au comptoir sans avoir à connaître les salaires, et le chef
         * d'établissement consulte les états sans tenir la caisse. Un couple
         * view/manage aurait obligé à tout accorder pour permettre une seule
         * de ces tâches.
         */
        'finance' => ['Finances', 'Finance', [
            'finance.view' => ['Consulter la situation financière', 'View financial position'],
            'finance.manage' => ['Paramétrer les tarifs et les frais annexes', 'Configure fees'],
            'finance.encaisser' => ['Encaisser les frais de scolarité et délivrer les reçus', 'Collect fees and issue receipts'],
            'finance.annuler' => ['Annuler un encaissement', 'Cancel a payment'],
            'finance.depenses' => ['Enregistrer et suivre les dépenses', 'Record and track expenses'],
            'finance.budget' => ['Allouer et suivre les budgets du personnel', 'Allocate and track staff budgets'],
            'finance.paie' => ['Préparer et arrêter la paie du personnel', 'Prepare and close payroll'],
            'finance.rapports' => ['Consulter les rapports et le bilan financier', 'View financial reports'],
        ]],
        'annonces' => ['Annonces', 'Announcements', [
            'annonces.view' => ['Consulter les annonces', 'View announcements'],
            'annonces.publish' => ['Publier des annonces', 'Publish announcements'],
        ]],
        'revendications' => ['Réclamations', 'Complaints', [
            'revendications.view' => ['Consulter les réclamations', 'View complaints'],
            'revendications.manage' => ['Enregistrer et traiter les réclamations', 'Record and process complaints'],
        ]],
        'conseil_classe' => ['Conseil de classe', 'Class council', [
            'conseil_classe.view' => ['Consulter les conseils de classe et les archives d\'années passées', 'View class councils and archived years'],
            'conseil_classe.manage' => ['Mener et valider les conseils de classe de fin d\'année', 'Run and validate end-of-year class councils'],
        ]],
    ];

    /**
     * Tous les codes de privilège, dans l'ordre du catalogue.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        $codes = [];

        foreach (self::MODULES as [, , $permissions]) {
            foreach (array_keys($permissions) as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public static function existe(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    /**
     * Libellé lisible d'un privilège, pour les messages d'erreur et l'interface.
     * Retombe sur le code brut si le privilège n'est pas catalogué — mieux vaut
     * un message technique qu'un message vide.
     */
    public static function libelle(string $code, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        foreach (self::MODULES as [, , $permissions]) {
            if (isset($permissions[$code])) {
                [$fr, $en] = $permissions[$code];

                return $locale === 'en' ? $en : $fr;
            }
        }

        return $code;
    }

    /**
     * Catalogue mis en forme pour l'écran de gestion des permissions.
     *
     * @return Collection<int, array{code: string, libelle: string, permissions: list<array{code: string, libelle: string}>}>
     */
    public static function parModule(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        return collect(self::MODULES)->map(fn (array $module, string $code) => [
            'code' => $code,
            'libelle' => $locale === 'en' ? $module[1] : $module[0],
            'permissions' => collect($module[2])
                ->map(fn (array $libelles, string $permission) => [
                    'code' => $permission,
                    'libelle' => $locale === 'en' ? $libelles[1] : $libelles[0],
                ])
                ->values()
                ->all(),
        ])->values();
    }
}
