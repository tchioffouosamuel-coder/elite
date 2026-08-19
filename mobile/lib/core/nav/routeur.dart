import 'package:flutter/material.dart';

import '../../features/admin/admin_pages.dart';
import '../../features/annonces/annonces_page.dart';
import '../../features/dashboard/tableau_bord_page.dart';
import '../../features/divers/modules_pages.dart';
import '../../features/finance/finance_pages.dart';
import '../../features/finance/paie_page.dart';
import '../../features/personnel/personnel_pages.dart';
import '../../features/transport/transport_pages.dart';
import '../ui/etats.dart';

/// Associe un chemin du web à l'écran mobile correspondant.
///
/// La correspondance est explicite plutôt que déduite : elle se relit en
/// regard de `destinations.dart` pour vérifier qu'aucune entrée de menu ne
/// mène nulle part.
Widget ecranPour(String chemin) {
  return switch (chemin) {
    // Vue d'ensemble
    '/' => const TableauBordPage(),
    '/annonces' => const CoquilleSimple(titre: 'Actualités', corps: AnnoncesPage()),

    // Personnel & structure
    '/personnel' => const PersonnelPage(),
    '/fonctions-referentiel' => const FonctionsPage(),
    '/departements' => const DepartementsPage(),
    '/niveaux' => const NiveauxScolairesPage(),

    // Classes & élèves
    '/mes-attributions' => const MesAttributionsPage(),
    '/sous-systemes' => const SousSystemesPage(),

    // Pédagogie
    '/matieres' => const MatieresPage(),
    '/progression' => const ProgressionPage(),

    // Santé
    '/infirmerie' => const InfirmeriePage(),

    // Transport scolaire
    '/bus/vehicules' => const BusVehiculesPage(),
    '/bus/trajets' => const BusTrajetsPage(),
    '/bus/arrets' => const BusArretsPage(),
    '/bus/eleves' => const BusAffectationsPage(),

    // Inventaire
    '/inventaire' => const InventairePage(),

    // Résultats
    '/palmares' => const PalmaresPage(),
    '/revendications' => const RevendicationsPage(),
    '/stats-pedagogiques' => const StatistiquesPage(
        titre: 'Stats pédagogiques', chemin: 'statistiques/pedagogiques'),
    '/stats-disciplinaires' => const StatistiquesPage(
        titre: 'Stats disciplinaires', chemin: 'statistiques/disciplinaires'),

    // Finances
    '/caisse' => const CaissePage(),
    '/tarifs' => const TarifsPage(),
    '/depenses' => const DepensesPage(),
    '/salaires' => const SalairesPage(),
    '/paie' => const PaiePage(),
    '/avances-salaire' => const AvancesSalairePage(),
    '/rapports-financiers' => const RapportsFinanciersPage(),

    // Administration
    '/niveaux-globaux' => const NiveauxGlobauxPage(),
    '/permissions' => const PrivilegesPage(),
    '/session' => const AnneeScolairePage(),
    '/parametres' => const ParametresPage(),

    _ => EcranAbsent(chemin: chemin),
  };
}

/// Enveloppe un corps sans barre propre dans un `Scaffold` titré.
class CoquilleSimple extends StatelessWidget {
  const CoquilleSimple({super.key, required this.titre, required this.corps});

  final String titre;
  final Widget corps;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(titre)),
      body: corps,
    );
  }
}

/// Destination déclarée au menu mais sans écran mobile.
///
/// Affichée franchement plutôt que masquée : mieux vaut dire ce qui n'est pas
/// encore là que laisser croire à une panne, et l'utilisateur sait qu'il doit
/// passer par le portail web.
class EcranAbsent extends StatelessWidget {
  const EcranAbsent({super.key, required this.chemin});

  final String chemin;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Bientôt disponible')),
      body: EtatVide(
        message: 'Ce module se consulte pour le moment depuis le portail web.',
        icone: Icons.desktop_windows_outlined,
      ),
    );
  }
}
