import 'package:flutter/material.dart';

import '../../features/accueil/accueil_page.dart';

/// Clé du `Scaffold` de la coquille.
///
/// Les écrans portent chacun leur propre `Scaffold` (barre de titre, bouton
/// flottant) : `Scaffold.of(context)` y trouverait donc le `Scaffold` interne,
/// qui n'a pas de tiroir. Cette clé désigne explicitement celui de la
/// coquille, seul à en posséder un.
final cleCoquille = GlobalKey<ScaffoldState>();

/// Barre de titre commune à tous les écrans.
///
/// Elle porte systématiquement le bouton du menu et l'état de
/// synchronisation : sans elle, chaque écran devait y penser, et aucun ne le
/// faisait — le tiroir devenait inatteignable autrement qu'en devinant le
/// geste de glissement depuis le bord.
class BarreApp extends StatelessWidget implements PreferredSizeWidget {
  const BarreApp({
    super.key,
    required this.titre,
    this.actions,
    this.bas,
  });

  final String titre;
  final List<Widget>? actions;
  final PreferredSizeWidget? bas;

  @override
  Size get preferredSize => Size.fromHeight(kToolbarHeight + (bas?.preferredSize.height ?? 0));

  @override
  Widget build(BuildContext context) {
    return AppBar(
      title: Text(titre),
      leading: IconButton(
        icon: const Icon(Icons.menu),
        tooltip: 'Menu',
        onPressed: () => cleCoquille.currentState?.openDrawer(),
      ),
      actions: [...?actions, const IndicateurSync()],
      bottom: bas,
    );
  }
}
