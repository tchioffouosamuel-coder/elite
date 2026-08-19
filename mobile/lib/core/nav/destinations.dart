import 'package:flutter/material.dart';

/// Carte de navigation de l'application, **calquée sur celle du web**
/// (`web/src/app/AppLayout.tsx`).
///
/// Volontairement déclarative et exhaustive : c'est elle qui rend la parité
/// avec le web vérifiable d'un coup d'œil, plutôt qu'éparpillée dans les
/// écrans. Un module ajouté au web se voit immédiatement manquant ici.
///
/// Les mêmes règles de visibilité qu'au web s'appliquent — privilège requis,
/// réservé au super administrateur, masqué pour un titulaire de classe, ou
/// limité à certains types d'établissement.
class Destination {
  const Destination({
    required this.chemin,
    required this.libelle,
    required this.icone,
    this.permission,
    this.superAdminSeul = false,
    this.masquerPourTitulaire = false,
    this.enseignantSeul = false,
    this.avecAttribution = false,
    this.types,
    this.horsLigne = false,
  });

  /// Chemin du web, repris tel quel : il sert de clé stable et facilite la
  /// correspondance écran par écran lors des revues.
  final String chemin;
  final String libelle;
  final IconData icone;
  final String? permission;
  final bool superAdminSeul;
  final bool masquerPourTitulaire;
  final bool enseignantSeul;

  /// Réservé aux comptes portant au moins une responsabilité nominative
  /// (professeur principal, surveillant général, censeur, conseiller
  /// d'orientation, chef de département) — un privilège ne suffirait pas à
  /// les distinguer, plusieurs métiers partageant les mêmes.
  final bool avecAttribution;

  /// Types d'établissement concernés ; `null` signifie « tous ».
  final List<String>? types;

  /// Consultable sans réseau, depuis la base locale. Le reste interroge
  /// l'API directement : répliquer un rapport de paie sur le téléphone de
  /// chaque enseignant n'aurait aucun sens.
  final bool horsLigne;
}

class GroupeDestinations {
  const GroupeDestinations({required this.libelle, required this.destinations});

  final String libelle;
  final List<Destination> destinations;
}

/// Les douze groupes du web, dans le même ordre.
const groupesNavigation = <GroupeDestinations>[
  GroupeDestinations(
    libelle: "Vue d'ensemble",
    destinations: [
      Destination(
        chemin: '/',
        libelle: 'Tableau de bord',
        icone: Icons.dashboard_outlined,
        permission: 'dashboard.view',
      ),
      Destination(
        chemin: '/annonces',
        libelle: 'Annonces',
        icone: Icons.campaign_outlined,
        permission: 'annonces.view',
        horsLigne: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Personnel & structure',
    destinations: [
      Destination(
        chemin: '/personnel',
        libelle: 'Personnel',
        icone: Icons.badge_outlined,
        permission: 'personnel.view',
        horsLigne: true,
      ),
      Destination(
        chemin: '/fonctions-referentiel',
        libelle: 'Fonctions',
        icone: Icons.work_outline,
        permission: 'personnel.manage',
        superAdminSeul: true,
      ),
      Destination(
        chemin: '/departements',
        libelle: 'Départements',
        icone: Icons.account_tree_outlined,
        permission: 'personnel.view',
      ),
      Destination(
        chemin: '/niveaux',
        libelle: 'Niveaux',
        icone: Icons.stairs_outlined,
        permission: 'pedagogie.view',
        types: ['primaire'],
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Classes & élèves',
    destinations: [
      Destination(
        chemin: '/ma-classe',
        libelle: 'Ma classe',
        icone: Icons.school_outlined,
        permission: 'classes.view',
        enseignantSeul: true,
        types: ['primaire', 'maternelle'],
        horsLigne: true,
      ),
      Destination(
        chemin: '/mes-attributions',
        libelle: 'Mes attributions',
        icone: Icons.manage_accounts_outlined,
        avecAttribution: true,
      ),
      Destination(
        chemin: '/classes',
        libelle: 'Classes',
        icone: Icons.meeting_room_outlined,
        permission: 'classes.view',
        masquerPourTitulaire: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/sous-systemes',
        libelle: 'Sous-systèmes',
        icone: Icons.layers_outlined,
        permission: 'classes.manage',
      ),
      Destination(
        chemin: '/eleves',
        libelle: 'Élèves',
        icone: Icons.person_outline,
        permission: 'eleves.view',
        masquerPourTitulaire: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/eleves/transferts',
        libelle: 'Transferts en masse',
        icone: Icons.swap_horiz,
        permission: 'eleves.manage',
        masquerPourTitulaire: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Pédagogie',
    destinations: [
      Destination(
        chemin: '/matieres',
        libelle: 'Matières',
        icone: Icons.menu_book_outlined,
        permission: 'pedagogie.view',
        masquerPourTitulaire: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/progression',
        libelle: 'Progression',
        icone: Icons.account_tree_outlined,
        permission: 'pedagogie.view',
        masquerPourTitulaire: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/ma-journee',
        libelle: 'Ma journée',
        icone: Icons.today_outlined,
        permission: 'appel.manage',
        enseignantSeul: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/scanner-qr',
        libelle: 'Scanner un code QR',
        icone: Icons.qr_code_scanner,
        permission: 'appel.manage',
        enseignantSeul: true,
        horsLigne: true,
      ),
      Destination(
        chemin: '/emploi-du-temps',
        libelle: 'Emploi du temps',
        icone: Icons.calendar_month_outlined,
        permission: 'emploi_du_temps.view',
        horsLigne: true,
      ),
      Destination(
        chemin: '/seances',
        libelle: 'Séances & appel',
        icone: Icons.checklist_outlined,
        permission: 'emploi_du_temps.view',
        horsLigne: true,
      ),
      Destination(
        chemin: '/codes-qr',
        libelle: 'Codes QR',
        icone: Icons.qr_code_2,
        permission: 'emploi_du_temps.manage',
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Discipline',
    destinations: [
      Destination(
        chemin: '/sanctions',
        libelle: 'Sanctions',
        icone: Icons.gavel_outlined,
        permission: 'discipline.view',
        types: ['secondaire'],
        horsLigne: true,
      ),
      Destination(
        chemin: '/stats-disciplinaires',
        libelle: 'Stats disciplinaires',
        icone: Icons.insights_outlined,
        permission: 'bulletins.view',
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Santé',
    destinations: [
      Destination(
        chemin: '/infirmerie',
        libelle: 'Infirmerie',
        icone: Icons.medical_services_outlined,
        permission: 'infirmerie.view',
        masquerPourTitulaire: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Transport scolaire',
    destinations: [
      Destination(
        chemin: '/bus/vehicules',
        libelle: 'Véhicules',
        icone: Icons.directions_bus_outlined,
        permission: 'bus.view',
        masquerPourTitulaire: true,
      ),
      Destination(
        chemin: '/bus/trajets',
        libelle: 'Trajets',
        icone: Icons.route_outlined,
        permission: 'bus.view',
        masquerPourTitulaire: true,
      ),
      Destination(
        chemin: '/bus/arrets',
        libelle: 'Arrêts',
        icone: Icons.location_on_outlined,
        permission: 'bus.view',
        masquerPourTitulaire: true,
      ),
      Destination(
        chemin: '/bus/eleves',
        libelle: 'Élèves du bus',
        icone: Icons.groups_outlined,
        permission: 'bus.view',
        masquerPourTitulaire: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Inventaire',
    destinations: [
      Destination(
        chemin: '/inventaire',
        libelle: 'Inventaire',
        icone: Icons.inventory_2_outlined,
        permission: 'inventaire.view',
        masquerPourTitulaire: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Résultats',
    destinations: [
      Destination(
        chemin: '/bulletins',
        libelle: 'Bulletins',
        icone: Icons.description_outlined,
        permission: 'bulletins.view',
      ),
      Destination(
        chemin: '/remplissage',
        libelle: 'Remplissage des notes',
        icone: Icons.fact_check_outlined,
        permission: 'notes.view',
      ),
      Destination(
        chemin: '/palmares',
        libelle: 'Palmarès',
        icone: Icons.emoji_events_outlined,
        permission: 'bulletins.view',
      ),
      Destination(
        chemin: '/stats-pedagogiques',
        libelle: 'Stats pédagogiques',
        icone: Icons.bar_chart_outlined,
        permission: 'bulletins.view',
      ),
      Destination(
        chemin: '/revendications',
        libelle: 'Réclamations',
        icone: Icons.balance_outlined,
        permission: 'revendications.view',
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Identification & sécurité',
    destinations: [
      Destination(
        chemin: '/identification',
        libelle: 'Photos & cartes',
        icone: Icons.badge_outlined,
        permission: 'eleves.view',
        masquerPourTitulaire: true,
      ),
      Destination(
        chemin: '/photos-examen',
        libelle: 'Photos DECC & OBC',
        icone: Icons.photo_camera_outlined,
        permission: 'eleves.view',
        masquerPourTitulaire: true,
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Finances',
    destinations: [
      Destination(
        chemin: '/caisse',
        libelle: 'Caisse',
        icone: Icons.account_balance_wallet_outlined,
        permission: 'finance.view',
      ),
      Destination(
        chemin: '/tarifs',
        libelle: 'Tarifs',
        icone: Icons.sell_outlined,
        permission: 'finance.view',
      ),
      Destination(
        chemin: '/depenses',
        libelle: 'Dépenses',
        icone: Icons.receipt_long_outlined,
        permission: 'finance.view',
      ),
      Destination(
        chemin: '/salaires',
        libelle: 'Salaires',
        icone: Icons.payments_outlined,
        permission: 'finance.paie',
      ),
      Destination(
        chemin: '/paie',
        libelle: 'Paie',
        icone: Icons.request_quote_outlined,
        permission: 'finance.paie',
      ),
      Destination(
        chemin: '/avances-salaire',
        libelle: 'Avances sur salaire',
        icone: Icons.savings_outlined,
        permission: 'finance.paie',
      ),
      Destination(
        chemin: '/rapports-financiers',
        libelle: 'Rapports financiers',
        icone: Icons.analytics_outlined,
        permission: 'finance.rapports',
      ),
    ],
  ),
  GroupeDestinations(
    libelle: 'Administration',
    destinations: [
      Destination(
        chemin: '/niveaux-globaux',
        libelle: 'Niveaux globaux',
        icone: Icons.layers_outlined,
        permission: 'niveaux.view',
      ),
      Destination(
        chemin: '/permissions',
        libelle: 'Privilèges',
        icone: Icons.verified_user_outlined,
        permission: 'personnel.manage',
        superAdminSeul: true,
      ),
      Destination(
        chemin: '/session',
        libelle: 'Année scolaire',
        icone: Icons.date_range_outlined,
        permission: 'ecoles.manage',
      ),
      Destination(
        chemin: '/parametres',
        libelle: 'Paramètres',
        icone: Icons.settings_outlined,
        permission: 'ecoles.manage',
      ),
    ],
  ),
];
