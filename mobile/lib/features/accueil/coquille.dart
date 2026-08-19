import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/nav/barre_app.dart';
import '../../core/nav/routeur.dart';
import '../../core/nav/tiroir.dart';
import '../../core/session/session.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/theme.dart';
import '../annonces/annonces_page.dart';
import '../qr/scan_qr_page.dart';
import 'accueil_page.dart';

/// Coquille de l'application : tiroir de navigation complet, plus une barre
/// inférieure pour les quelques destinations du quotidien.
///
/// Les deux se complètent au lieu de se concurrencer : le tiroir donne accès
/// aux douze groupes du web, la barre évite d'ouvrir ce tiroir quarante fois
/// par jour pour l'appel. Sur tablette, la barre disparaît au profit du
/// tiroir permanent — l'espace latéral existe, autant s'en servir.
class Coquille extends ConsumerStatefulWidget {
  const Coquille({super.key});

  @override
  ConsumerState<Coquille> createState() => _CoquilleState();
}

class _CoquilleState extends ConsumerState<Coquille> with WidgetsBindingObserver {
  String _chemin = '/';

  /// Destinations de la barre inférieure : celles qu'un enseignant ouvre
  /// plusieurs fois par jour. Volontairement peu nombreuses — au-delà de
  /// cinq, les libellés se tronquent et les cibles tactiles rétrécissent.
  static const _raccourcis = [
    (chemin: '/ma-journee', icone: Icons.today_outlined, actif: Icons.today, libelle: 'Ma journée'),
    (chemin: '/classes', icone: Icons.groups_outlined, actif: Icons.groups, libelle: 'Classes'),
    (chemin: '/eleves', icone: Icons.person_outline, actif: Icons.person, libelle: 'Élèves'),
    (chemin: '/annonces', icone: Icons.notifications_none, actif: Icons.notifications, libelle: 'Actualités'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Synchronisation à l'ouverture : sans elle, un utilisateur déjà connecté
    // — le cas de tous les jours — ouvre l'app sur des données figées.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(ref.read(syncServiceProvider.notifier).synchroniser());
      _poserCheminInitial();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState etat) {
    if (etat == AppLifecycleState.resumed) {
      unawaited(ref.read(syncServiceProvider.notifier).synchroniser());
    }
  }

  /// Ouvre sur la première destination réellement accessible.
  ///
  /// Un enseignant n'a pas `dashboard.view` : le poser sur le tableau de bord
  /// lui montrerait un refus dès l'ouverture. On le place sur « Ma journée »,
  /// et l'économe sur ce que ses privilèges autorisent.
  void _poserCheminInitial() {
    final session = ref.read(sessionProvider);
    if (session == null) return;

    final groupes = destinationsVisibles(session);
    if (groupes.isEmpty) return;

    final chemins = groupes.expand((g) => g.destinations).map((d) => d.chemin).toSet();
    if (chemins.contains(_chemin)) return;

    final raccourci = _raccourcis.map((r) => r.chemin).firstWhere(
          chemins.contains,
          orElse: () => groupes.first.destinations.first.chemin,
        );

    setState(() => _chemin = raccourci);
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    final large = !Ruptures.estTelephone(context);

    if (session == null) return const SizedBox.shrink();

    final chemins =
        destinationsVisibles(session).expand((g) => g.destinations).map((d) => d.chemin).toSet();
    final raccourcis = _raccourcis.where((r) => chemins.contains(r.chemin)).toList();
    final indexActif = raccourcis.indexWhere((r) => r.chemin == _chemin);

    final tiroir = TiroirNavigation(
      cheminActif: _chemin,
      onNaviguer: (destination) {
        // Sur téléphone le tiroir est modal : il faut le refermer. Sur
        // tablette il reste affiché, il n'y a rien à fermer.
        if (!large) Navigator.of(context).pop();
        setState(() => _chemin = destination.chemin);
      },
    );

    final corps = _corps(_chemin);

    return Scaffold(
      key: cleCoquille,
      // Le tiroir n'est branché que sur téléphone : sur tablette il est déjà
      // dans la mise en page, l'attacher au Scaffold le dupliquerait.
      drawer: large ? null : tiroir,
      body: large
          ? Row(
              children: [
                SizedBox(width: 290, child: tiroir),
                const VerticalDivider(width: 1),
                Expanded(child: corps),
              ],
            )
          : corps,
      floatingActionButton: _chemin == '/ma-journee' && session.peut('appel.manage')
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const ScanQrPage()),
              ),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scanner'),
            )
          : null,
      bottomNavigationBar: large || raccourcis.length < 2
          ? null
          : NavigationBar(
              selectedIndex: indexActif < 0 ? 0 : indexActif,
              onDestinationSelected: (i) => setState(() => _chemin = raccourcis[i].chemin),
              destinations: [
                for (final r in raccourcis)
                  NavigationDestination(
                    icon: Icon(r.icone),
                    selectedIcon: Icon(r.actif),
                    label: r.libelle,
                  ),
              ],
            ),
    );
  }

  /// Les écrans hors-ligne vivent dans la base locale et ne passent pas par
  /// le routeur en ligne : ils sont rendus ici, sous une barre commune qui
  /// porte le bouton du tiroir et l'état de synchronisation.
  Widget _corps(String chemin) {
    final (titre, vue) = switch (chemin) {
      '/ma-journee' => ('Ma journée', const MaJourneeVue()),
      '/classes' || '/ma-classe' => ('Classes', const ListeClassesVue()),
      '/eleves' => ('Élèves', const ListeElevesVue()),
      '/annonces' => ('Actualités', const AnnoncesPage()),
      _ => (null, null),
    };

    if (vue == null) return ecranPour(chemin);

    return Scaffold(
      appBar: BarreApp(titre: titre!),
      body: vue,
    );
  }
}
