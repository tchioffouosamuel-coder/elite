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
 * d'erreur : « Il vous manque le privilège “Modifier la fiche d'un élève” »
 * est plus exploitable pour un chef d'établissement que « eleves.update ».
 */
class CataloguePermissions
{
    /**
     * module => [libellé du module, [code => [libellé fr, libellé en]]].
     *
     * Un code s'écrit `entité.action` : une action par privilège (`create`,
     * `update`, `delete`, `import`, ou un verbe métier — `valider`,
     * `transferer`, `encaisser`…), jamais un « gérer » qui les engloberait
     * toutes. Le module ne sert qu'à regrouper les cases à l'écran : il peut
     * porter plusieurs entités (les matières et leurs affectations).
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
            'dashboard.pilotage' => ['Accéder au pilotage en temps réel', 'Access real-time monitoring'],
        ]],
        /*
         * `ecoles.update` sert aussi de marqueur de direction : qui peut
         * modifier la fiche de l'établissement travaille sur l'école entière
         * et n'est pas borné à une liste de classes (cf. Perimetre::estBorne()).
         */
        'ecoles' => ['Établissement', 'School', [
            'ecoles.update' => ["Modifier la fiche de l'établissement (identité, logo, images)", 'Edit the school profile'],
            'parametres.update' => ["Modifier les paramètres de l'établissement", 'Edit the school settings'],
            'annees_scolaires.view' => ['Consulter les années scolaires', 'View school years'],
            'annees_scolaires.create' => ['Créer une année scolaire', 'Create a school year'],
            'annees_scolaires.update' => ['Modifier une année scolaire', 'Edit a school year'],
            'annees_scolaires.activer' => ['Activer une année scolaire', 'Activate a school year'],
            'annees_scolaires.archiver' => ['Archiver une année scolaire et basculer vers la suivante', 'Archive a school year and roll over to the next'],
            'annees_scolaires.seances' => ["Générer ou supprimer les séances d'une année entière", 'Generate or delete the sessions of a whole year'],
            'trimestres.create' => ['Créer un trimestre', 'Create a term'],
            'trimestres.update' => ['Modifier un trimestre', 'Edit a term'],
            'trimestres.activer' => ['Activer un trimestre', 'Activate a term'],
            'trimestres.seances' => ["Générer ou supprimer les séances d'un trimestre", 'Generate or delete the sessions of a term'],
        ]],
        'personnel' => ['Personnel', 'Staff', [
            'personnel.view' => ['Consulter le personnel', 'View staff'],
            'personnel.create' => ['Ajouter un agent', 'Add a staff member'],
            'personnel.update' => ["Modifier la fiche d'un agent", 'Edit a staff member'],
            'personnel.delete' => ['Supprimer un agent', 'Delete a staff member'],
            'personnel.import' => ['Importer le personnel', 'Import staff'],
            'personnel.archiver' => ['Archiver et réactiver un agent', 'Archive and reactivate a staff member'],
            'personnel.comptes' => ['Créer les comptes de connexion du personnel et éditer leurs identifiants', 'Create staff login accounts and print their credentials'],
            'personnel.presences' => ['Importer les présences journalières et annuler une validation de présence', 'Import daily attendance and cancel an attendance validation'],
            'personnel.attestations' => ["Délivrer les attestations de l'employeur", 'Issue employer certificates'],
        ]],
        'referentiels_personnel' => ['Départements, fonctions et banques', 'Departments, positions and banks', [
            'departements.create' => ['Créer un département', 'Create a department'],
            'departements.update' => ['Modifier un département', 'Edit a department'],
            'departements.delete' => ['Supprimer un département', 'Delete a department'],
            'departements.import' => ['Importer les départements', 'Import departments'],
            'fonctions.create' => ['Créer une fonction', 'Create a position'],
            'fonctions.update' => ['Modifier une fonction', 'Edit a position'],
            'fonctions.delete' => ['Supprimer une fonction', 'Delete a position'],
            'fonctions.import' => ['Importer les fonctions', 'Import positions'],
            'banques.create' => ['Créer une banque', 'Create a bank'],
            'banques.update' => ['Modifier une banque', 'Edit a bank'],
            'banques.delete' => ['Supprimer une banque', 'Delete a bank'],
            'banques.import' => ['Importer les banques', 'Import banks'],
            'banques.mouvements' => ['Enregistrer les dépôts et retraits bancaires', 'Record bank deposits and withdrawals'],
            'regles_seance.create' => ['Créer une règle de validation des séances', 'Create a session validation rule'],
            'regles_seance.update' => ['Modifier une règle de validation des séances', 'Edit a session validation rule'],
            'regles_seance.delete' => ['Supprimer une règle de validation des séances', 'Delete a session validation rule'],
        ]],
        'classes' => ['Classes', 'Classes', [
            'classes.view' => ['Consulter les classes', 'View classes'],
            'classes.create' => ['Créer une classe', 'Create a class'],
            'classes.update' => ['Modifier une classe et ses responsables', 'Edit a class and its staff in charge'],
            'classes.delete' => ['Supprimer une classe', 'Delete a class'],
            'classes.import' => ['Importer les classes', 'Import classes'],
            'classes.fusionner' => ['Fusionner des classes', 'Merge classes'],
            'sous_systemes.view' => ['Consulter les sous-systèmes', 'View sub-systems'],
            'sous_systemes.create' => ['Créer un sous-système', 'Create a sub-system'],
            'sous_systemes.update' => ['Modifier un sous-système', 'Edit a sub-system'],
            'sous_systemes.delete' => ['Supprimer un sous-système', 'Delete a sub-system'],
            'sous_systemes.import' => ['Importer les sous-systèmes', 'Import sub-systems'],
        ]],
        'niveaux' => ['Niveaux globaux', 'Global levels', [
            'niveaux.view' => ['Consulter les niveaux', 'View levels'],
            'niveaux.create' => ['Créer un niveau', 'Create a level'],
            'niveaux.update' => ['Modifier un niveau', 'Edit a level'],
            'niveaux.delete' => ['Supprimer un niveau', 'Delete a level'],
            'niveaux.import' => ['Importer les niveaux', 'Import levels'],
        ]],
        'eleves' => ['Élèves', 'Pupils', [
            'eleves.view' => ['Consulter les élèves', 'View pupils'],
            'eleves.situation' => ['Consulter la situation financière et le transport des élèves de ses classes (lecture seule)', "View the fees and transport of one's own pupils (read-only)"],
            'eleves.create' => ['Inscrire un élève', 'Enrol a pupil'],
            'eleves.update' => ["Modifier la fiche, la classe et la photo d'un élève", "Edit a pupil's record, class and photo"],
            'eleves.delete' => ['Supprimer un élève', 'Delete a pupil'],
            'eleves.import' => ['Importer des élèves', 'Import pupils'],
            'eleves.transferer' => ['Transférer des élèves de classe en lot, ou vers un autre établissement', 'Bulk transfer pupils to another class, or to another school'],
            'eleves.fusionner' => ["Fusionner les doublons d'élèves", 'Merge duplicate pupils'],
            'eleves.comptes' => ['Gérer les comptes du portail élève et éditer leurs identifiants', 'Manage pupil portal accounts and print their credentials'],
            'matricules_nationaux.update' => ['Modifier un matricule national', 'Edit a national ID number'],
            'matricules_nationaux.import' => ['Importer les matricules nationaux', 'Import national ID numbers'],
        ]],
        'tuteurs' => ['Parents et tuteurs', 'Parents and guardians', [
            'tuteurs.view' => ["Consulter les tuteurs et l'usage du portail parent", 'View guardians and parent portal usage'],
            'tuteurs.update' => ['Rattacher des enfants à un tuteur', 'Link children to a guardian'],
            'tuteurs.delete' => ['Supprimer un tuteur', 'Delete a guardian'],
            'tuteurs.fusionner' => ['Fusionner les doublons de tuteurs', 'Merge duplicate guardians'],
            'tuteurs.comptes' => ['Gérer les comptes du portail parent et éditer leurs identifiants', 'Manage parent portal accounts and print their credentials'],
        ]],
        /*
         * Ce que les familles déposent depuis le portail parent, et que
         * l'administration instruit : consulter une demande et la trancher
         * sont deux gestes distincts.
         */
        'demarches' => ['Préinscriptions et démarches des parents', 'Pre-registrations and parent requests', [
            'preinscriptions.view' => ['Consulter les préinscriptions', 'View pre-registrations'],
            'preinscriptions.create' => ['Enregistrer une préinscription', 'Record a pre-registration'],
            'preinscriptions.update' => ['Modifier une préinscription', 'Edit a pre-registration'],
            'preinscriptions.delete' => ['Supprimer une préinscription', 'Delete a pre-registration'],
            'preinscriptions.import' => ['Importer des préinscriptions', 'Import pre-registrations'],
            'preinscriptions.valider' => ['Valider ou rejeter une préinscription', 'Approve or reject a pre-registration'],
            'modifications_eleves.view' => ['Consulter les modifications de fiche proposées par les parents', 'View record changes proposed by parents'],
            'modifications_eleves.valider' => ['Valider ou rejeter une modification de fiche', 'Approve or reject a record change'],
            'justifications.view' => ["Consulter les justifications d'absence déposées par les parents", 'View absence justifications submitted by parents'],
            'observations.view' => ['Consulter les observations des parents', 'View parent observations'],
            'observations.repondre' => ['Répondre aux observations des parents', 'Reply to parent observations'],
        ]],
        'pedagogie' => ['Pédagogie', 'Teaching', [
            'pedagogie.view' => ['Consulter matières, affectations et progression', 'View teaching data'],
            'matieres.create' => ['Créer une matière', 'Create a subject'],
            'matieres.update' => ['Modifier une matière', 'Edit a subject'],
            'matieres.delete' => ['Supprimer une matière', 'Delete a subject'],
            'matieres.import' => ['Importer les matières', 'Import subjects'],
            'matieres.fusionner' => ['Fusionner des matières', 'Merge subjects'],
            'affectations.create' => ['Affecter une matière à une classe', 'Assign a subject to a class'],
            'affectations.update' => ['Modifier une affectation (enseignant, coefficient)', 'Edit an assignment (teacher, coefficient)'],
            'affectations.delete' => ["Retirer une matière d'une classe", 'Remove a subject from a class'],
            'tronc_commun.create' => ['Créer un groupe de tronc commun', 'Create a common-core group'],
            'tronc_commun.delete' => ['Supprimer un groupe de tronc commun', 'Delete a common-core group'],
            'calendrier_scolaire.create' => ['Ajouter un jour au calendrier scolaire', 'Add a day to the school calendar'],
            'calendrier_scolaire.delete' => ['Retirer un jour du calendrier scolaire', 'Remove a day from the school calendar'],
        ]],
        'competences' => ['Compétences et appréciations', 'Competences and appraisals', [
            'competences.create' => ['Créer une compétence', 'Create a competence'],
            'competences.update' => ['Modifier une compétence', 'Edit a competence'],
            'competences.delete' => ['Supprimer une compétence', 'Delete a competence'],
            'competences.import' => ['Importer les compétences', 'Import competences'],
            'competences.attribuer' => ['Attribuer les compétences aux classes', 'Assign competences to classes'],
            'appreciations.create' => ['Créer une appréciation', 'Create an appraisal'],
            'appreciations.update' => ['Modifier une appréciation', 'Edit an appraisal'],
            'appreciations.delete' => ['Supprimer une appréciation', 'Delete an appraisal'],
            'appreciations.import' => ['Importer les appréciations', 'Import appraisals'],
            'niveaux_scolaires.create' => ["Créer un niveau d'enseignement", 'Create a teaching level'],
            'niveaux_scolaires.update' => ["Modifier un niveau d'enseignement", 'Edit a teaching level'],
            'niveaux_scolaires.delete' => ["Supprimer un niveau d'enseignement", 'Delete a teaching level'],
        ]],
        'progression' => ['Progression et évaluations', 'Progress and assessments', [
            'progression.update' => ['Modifier les fiches de progression', 'Edit progress sheets'],
            'progression.import' => ['Importer les fiches de progression', 'Import progress sheets'],
            'evaluations.create' => ['Programmer une évaluation', 'Schedule an assessment'],
            'evaluations.update' => ['Modifier une évaluation', 'Edit an assessment'],
            'evaluations.delete' => ['Supprimer une évaluation', 'Delete an assessment'],
        ]],
        'notes' => ['Notes', 'Marks', [
            'notes.view' => ['Consulter les notes et les classements', 'View marks'],
            'notes.create' => ['Saisir et importer des notes', 'Enter marks'],
        ]],
        'appel' => ['Appel', 'Attendance', [
            'appel.saisir' => ["Faire l'appel et déclarer les leçons traitées", 'Take attendance'],
        ]],
        'discipline' => ['Discipline', 'Discipline', [
            'discipline.view' => ['Consulter absences et sanctions', 'View discipline records'],
            'absences.saisir' => ['Enregistrer et corriger les absences', 'Record and correct absences'],
            'sanctions.create' => ['Prononcer une sanction', 'Issue a sanction'],
            'sanctions.update' => ['Modifier une sanction', 'Edit a sanction'],
            'sanctions.delete' => ['Supprimer une sanction', 'Delete a sanction'],
        ]],
        'infirmerie' => ['Infirmerie', 'Infirmary', [
            'infirmerie.view' => ["Consulter les visites à l'infirmerie", 'View infirmary visits'],
            'infirmerie.create' => ["Enregistrer une visite à l'infirmerie", 'Record an infirmary visit'],
            'infirmerie.update' => ["Modifier une visite à l'infirmerie", 'Edit an infirmary visit'],
            'infirmerie.delete' => ["Supprimer une visite à l'infirmerie", 'Delete an infirmary visit'],
            'infirmerie.import' => ["Importer les visites à l'infirmerie", 'Import infirmary visits'],
            'malaises.create' => ['Ajouter un malaise au référentiel', 'Add an ailment to the reference list'],
            'malaises.update' => ['Modifier un malaise du référentiel', 'Edit an ailment of the reference list'],
            'malaises.delete' => ['Supprimer un malaise du référentiel', 'Delete an ailment from the reference list'],
        ]],
        'bus' => ['Transport scolaire', 'School transport', [
            'bus.view' => ['Consulter véhicules, trajets et affectations', 'View vehicles, routes and assignments'],
            'bus.souscrire' => ['Souscrire ou retirer des élèves au transport, encaisser leurs paiements', 'Subscribe or remove pupils from transport, collect their payments'],
            'bus.ramassage' => ['Pointer les élèves pris en charge à chaque arrêt', 'Check off pupils picked up at each stop'],
            'bus.remplacement' => ["Confier l'itinéraire d'un chauffeur empêché à un autre", "Hand an unavailable driver's route over to another"],
            'bus_vehicules.create' => ['Ajouter un véhicule', 'Add a vehicle'],
            'bus_vehicules.update' => ['Modifier un véhicule', 'Edit a vehicle'],
            'bus_vehicules.delete' => ['Supprimer un véhicule', 'Delete a vehicle'],
            'bus_vehicules.import' => ['Importer les véhicules', 'Import vehicles'],
            'bus_trajets.create' => ['Créer un trajet', 'Create a route'],
            'bus_trajets.update' => ['Modifier un trajet', 'Edit a route'],
            'bus_trajets.delete' => ['Supprimer un trajet', 'Delete a route'],
            'bus_trajets.import' => ['Importer les trajets', 'Import routes'],
            'bus_trajets.notifier' => ["Notifier les parents des élèves d'un trajet", "Notify the parents of a route's pupils"],
            'bus_arrets.create' => ['Ajouter un arrêt', 'Add a stop'],
            'bus_arrets.update' => ['Modifier un arrêt', 'Edit a stop'],
            'bus_arrets.delete' => ['Supprimer un arrêt', 'Delete a stop'],
            'bus_arrets.import' => ['Importer les arrêts', 'Import stops'],
        ]],
        'inventaire' => ['Inventaire', 'Inventory', [
            'inventaire.view' => ['Consulter l\'inventaire du matériel', 'View the equipment inventory'],
            'inventaire.create' => ["Ajouter un article à l'inventaire", 'Add an inventory item'],
            'inventaire.update' => ["Modifier un article de l'inventaire", 'Edit an inventory item'],
            'inventaire.delete' => ["Supprimer un article de l'inventaire", 'Delete an inventory item'],
            'inventaire.import' => ["Importer les articles de l'inventaire", 'Import inventory items'],
            'inventaire.etiquettes' => ['Générer les codes-barres et imprimer les étiquettes', 'Generate barcodes and print labels'],
            'demandes_articles.view' => ["Consulter les demandes d'articles du personnel", 'View staff item requests'],
            'demandes_articles.valider' => ["Valider ou rejeter une demande d'article", 'Approve or reject an item request'],
        ]],
        /*
         * Distinct de `inventaire` (matériel consommable/vendable) : ce
         * module couvre le bâti et le mobilier fixe recensés au rapport de
         * rentrée MINEDUB — salles de classe, points d'eau, tables-bancs…
         */
        'infrastructures' => ['Infrastructures et mobilier', 'Infrastructure and furniture', [
            'infrastructures.view' => ['Consulter les infrastructures et le mobilier', 'View infrastructure and furniture'],
            'infrastructures.create' => ['Ajouter une infrastructure', 'Add an infrastructure'],
            'infrastructures.update' => ['Modifier une infrastructure', 'Edit an infrastructure'],
            'infrastructures.delete' => ['Supprimer une infrastructure', 'Delete an infrastructure'],
            'infrastructures.import' => ['Importer les infrastructures', 'Import infrastructure'],
            'equipements.create' => ['Ajouter un équipement ou mobilier', 'Add equipment or furniture'],
            'equipements.update' => ['Modifier un équipement ou mobilier', 'Edit equipment or furniture'],
            'equipements.delete' => ['Supprimer un équipement ou mobilier', 'Delete equipment or furniture'],
            'equipements.import' => ['Importer les équipements et le mobilier', 'Import equipment and furniture'],
        ]],
        /*
         * Rubriques du rapport de rentrée MINEDUB qui ne rentrent dans aucun
         * autre module : visites d'autorités, activités pédagogiques/EPS/
         * FENASSCO, vente de denrées et blocs de texte libre (sécurité,
         * gouvernements d'enfants, doléances…).
         */
        'rapport_rentree' => ['Rapport de rentrée', 'Back-to-school report', [
            'rapport_rentree.view' => ['Consulter le rapport de rentrée', 'View the back-to-school report'],
            'rapport_rentree.update' => ['Rédiger les textes du rapport de rentrée', 'Write the back-to-school report texts'],
            'visites_autorites.create' => ["Enregistrer une visite d'autorité", 'Record an official visit'],
            'visites_autorites.update' => ["Modifier une visite d'autorité", 'Edit an official visit'],
            'visites_autorites.delete' => ["Supprimer une visite d'autorité", 'Delete an official visit'],
            'activites_rentree.create' => ['Enregistrer une activité de rentrée', 'Record a back-to-school activity'],
            'activites_rentree.update' => ['Modifier une activité de rentrée', 'Edit a back-to-school activity'],
            'activites_rentree.delete' => ['Supprimer une activité de rentrée', 'Delete a back-to-school activity'],
            'ventes_denrees.create' => ['Enregistrer une vente de denrées', 'Record a food sale'],
            'ventes_denrees.update' => ['Modifier une vente de denrées', 'Edit a food sale'],
            'ventes_denrees.delete' => ['Supprimer une vente de denrées', 'Delete a food sale'],
        ]],
        /*
         * Rapport de fin de trimestre MINEDUB : blocs de texte libre
         * (introduction, observations, difficultés rencontrées, conclusion)
         * — le reste du contenu (effectifs, fréquentation, pédagogie) vient
         * déjà des modules Élèves/Discipline/Progression/Résultats.
         */
        'rapport_trimestre' => ['Rapport de fin de trimestre', 'End-of-term report', [
            'rapport_trimestre.view' => ['Consulter le rapport de fin de trimestre', 'View the end-of-term report'],
            'rapport_trimestre.update' => ['Rédiger les textes du rapport de fin de trimestre', 'Write the end-of-term report texts'],
        ]],
        /*
         * Le comptoir se sépare de l'inventaire : le vendeur écoule le stock
         * sans avoir à modifier la fiche des articles, et l'économe tient
         * l'inventaire sans forcément tenir la caisse de la boutique. Vendre,
         * annuler une vente et approvisionner sont trois gestes distincts,
         * pour la même raison qu'encaisser l'est de paramétrer les tarifs,
         * côté finances.
         */
        'point_de_vente' => ['Point de vente', 'Point of sale', [
            'point_de_vente.view' => ['Consulter les ventes et les entrées de stock', 'View sales and stock entries'],
            'point_de_vente.vendre' => ['Vendre au comptoir et éditer les factures', 'Sell at the counter and issue invoices'],
            'point_de_vente.annuler' => ['Annuler une vente', 'Cancel a sale'],
            'point_de_vente.approvisionner' => ['Enregistrer les entrées de stock', 'Record stock entries'],
        ]],
        'bulletins' => ['Bulletins et statistiques', 'Reports and statistics', [
            'bulletins.view' => ['Consulter bulletins, palmarès et statistiques', 'View reports'],
            'bulletins.publish' => ['Publier les bulletins', 'Publish reports'],
        ]],
        'emploi_du_temps' => ['Emploi du temps', 'Timetable', [
            'emploi_du_temps.view' => ["Consulter l'emploi du temps et les séances", 'View the timetable'],
            'emploi_du_temps.create' => ["Ajouter un créneau à l'emploi du temps (ou le copier d'une autre classe)", 'Add a timetable slot (or copy it from another class)'],
            'emploi_du_temps.update' => ["Modifier un créneau de l'emploi du temps", 'Edit a timetable slot'],
            'emploi_du_temps.delete' => ["Supprimer un créneau de l'emploi du temps", 'Delete a timetable slot'],
            'emploi_du_temps.import' => ["Importer l'emploi du temps", 'Import the timetable'],
            'edt_elements.create' => ["Créer un élément type d'emploi du temps", 'Create a timetable template item'],
            'edt_elements.update' => ["Modifier un élément type d'emploi du temps", 'Edit a timetable template item'],
            'edt_elements.delete' => ["Supprimer un élément type d'emploi du temps", 'Delete a timetable template item'],
            'edt_elements.appliquer' => ['Appliquer un élément type aux classes', 'Apply a template item to classes'],
            'seances.create' => ['Créer une séance', 'Create a session'],
            'seances.update' => ['Modifier une séance', 'Edit a session'],
            'seances.delete' => ['Supprimer une séance', 'Delete a session'],
            'seances.generer' => ["Générer ou supprimer en masse les séances d'une classe", "Bulk generate or delete a class's sessions"],
            'salles.create' => ['Créer une salle', 'Create a room'],
            'salles.update' => ['Modifier une salle', 'Edit a room'],
            'salles.delete' => ['Supprimer une salle', 'Delete a room'],
        ]],
        /*
         * Les finances se découpent par métier plutôt que par opération :
         * l'économe encaisse au comptoir sans avoir à connaître les salaires,
         * et le chef d'établissement consulte les états sans tenir la caisse.
         * Le paramétrage (tarifs, échéancier) et les corrections à la
         * situation d'un élève (remises, moratoires, dettes) ont leurs
         * propres modules ci-dessous.
         */
        'finance' => ['Finances', 'Finance', [
            'finance.view' => ['Consulter la situation financière', 'View financial position'],
            'finance.encaisser' => ['Encaisser les frais de scolarité et délivrer les reçus', 'Collect fees and issue receipts'],
            'finance.annuler' => ['Annuler un encaissement', 'Cancel a payment'],
            'finance.depenses' => ['Enregistrer et suivre les dépenses', 'Record and track expenses'],
            'finance.budget' => ['Allouer et suivre les budgets du personnel', 'Allocate and track staff budgets'],
            'finance.paie' => ['Préparer et arrêter la paie du personnel', 'Prepare and close payroll'],
            'finance.rapports' => ['Consulter les rapports et le bilan financier', 'View financial reports'],
        ]],
        'tarifs' => ['Tarifs et échéancier', 'Fees and payment schedule', [
            'tarifs.update' => ["Fixer le tarif d'une classe et le répercuter sur les dossiers", "Set a class's fee and apply it to pupil accounts"],
            'tarifs.delete' => ["Supprimer le tarif d'une classe", "Delete a class's fee"],
            'tarifs.import' => ['Importer la grille des frais', 'Import the fee grid'],
            'frais_annexes.create' => ['Créer un frais annexe', 'Create an additional fee'],
            'frais_annexes.update' => ['Modifier un frais annexe', 'Edit an additional fee'],
            'frais_annexes.delete' => ['Désactiver un frais annexe', 'Disable an additional fee'],
            'frais_annexes.import' => ['Importer les frais annexes', 'Import additional fees'],
            'tranches_scolarite.update' => ["Modifier l'échéancier de la scolarité", 'Edit the tuition payment schedule'],
            'tranches_scolarite.import' => ["Importer l'échéancier de la scolarité", 'Import the tuition payment schedule'],
        ]],
        /*
         * Corrections à la situation d'un élève, réservées à qui peut décider
         * d'un montant — pas au simple encaissement.
         */
        'ajustements_finance' => ['Remises, moratoires et dettes antérieures', 'Discounts, payment deferrals and past debts', [
            'remises.create' => ['Accorder une remise', 'Grant a discount'],
            'remises.update' => ['Modifier une remise', 'Edit a discount'],
            'remises.delete' => ['Supprimer une remise', 'Delete a discount'],
            'moratoires.create' => ['Accorder un moratoire', 'Grant a payment deferral'],
            'moratoires.delete' => ['Supprimer un moratoire', 'Delete a payment deferral'],
            'dettes_anterieures.create' => ['Enregistrer une dette antérieure', 'Record a past debt'],
            'dettes_anterieures.delete' => ['Supprimer une dette antérieure', 'Delete a past debt'],
            'dettes_anterieures.import' => ['Importer les dettes antérieures', 'Import past debts'],
            'dettes_anterieures.oublier' => ["Abandonner les dettes antérieures d'un élève", "Write off a pupil's past debts"],
        ]],
        'rentree_scolaire' => ['Rentrée scolaire (budget, assurances, conseil, APEE)', 'School opening (budget, insurance, council, PTA)', [
            'budget_fonctionnement.update' => ['Modifier le budget de fonctionnement', 'Edit the operating budget'],
            'assurances_scolaires.create' => ['Enregistrer une assurance scolaire', 'Record a school insurance'],
            'assurances_scolaires.update' => ['Modifier une assurance scolaire', 'Edit a school insurance'],
            'assurances_scolaires.delete' => ['Supprimer une assurance scolaire', 'Delete a school insurance'],
            'conseil_ecole.update' => ["Renseigner le conseil d'école", 'Fill in the school council'],
            'apee.update' => ["Renseigner l'APEE", 'Fill in the PTA'],
        ]],
        'annonces' => ['Annonces', 'Announcements', [
            'annonces.view' => ['Consulter les annonces', 'View announcements'],
            'annonces.publish' => ['Publier des annonces', 'Publish announcements'],
        ]],
        'bibliotheque' => ['Bibliothèque numérique', 'Digital library', [
            'bibliotheque.view' => ['Consulter la bibliothèque numérique', 'View the digital library'],
            'bibliotheque.create' => ['Déposer des documents', 'Upload documents'],
            'bibliotheque.update' => ['Modifier un document', 'Edit a document'],
            'bibliotheque.delete' => ['Supprimer un document', 'Delete a document'],
        ]],
        'revendications' => ['Réclamations', 'Complaints', [
            'revendications.view' => ['Consulter les réclamations', 'View complaints'],
            'revendications.create' => ['Enregistrer une réclamation', 'Record a complaint'],
            'revendications.update' => ['Traiter et modifier une réclamation', 'Process and edit a complaint'],
            'revendications.delete' => ['Supprimer une réclamation', 'Delete a complaint'],
        ]],
        'conseil_classe' => ['Conseil de classe', 'Class council', [
            'conseil_classe.view' => ['Consulter les conseils de classe et les archives d\'années passées', 'View class councils and archived years'],
            'conseil_classe.update' => ['Mener un conseil de classe (seuil, destinations, exclusions et grâces)', 'Run a class council (threshold, destinations, exclusions and pardons)'],
            'conseil_classe.valider' => ["Valider un conseil de classe de fin d'année", 'Validate an end-of-year class council'],
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
     * Développe une liste où un motif `entité.*` vaut pour toutes les actions
     * de l'entité. Les compositions par défaut (rôles, attributions) disent
     * ainsi « tout sur les sanctions » sans réécrire la liste à chaque action
     * ajoutée au catalogue ; un code exact passe tel quel.
     *
     * @param  list<string>  $motifs
     * @return list<string>
     */
    public static function developper(array $motifs): array
    {
        $codes = [];

        foreach ($motifs as $motif) {
            if (! str_ends_with($motif, '.*')) {
                $codes[] = $motif;

                continue;
            }

            $prefixe = substr($motif, 0, -1);

            foreach (self::codes() as $code) {
                if (str_starts_with($code, $prefixe)) {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
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
