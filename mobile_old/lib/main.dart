import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/session/session.dart';
import 'core/ui/theme.dart';
import 'features/accueil/coquille.dart';
import 'features/auth/connexion_page.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final conteneur = ProviderContainer();
  // La session est restaurée avant le premier rendu : sans ça, l'app
  // afficherait brièvement l'écran de connexion à chaque ouverture, y compris
  // hors réseau où l'utilisateur ne pourrait justement pas se reconnecter.
  await conteneur.read(sessionProvider.notifier).restaurer();

  runApp(
    UncontrolledProviderScope(
      container: conteneur,
      child: const ElitesApp(),
    ),
  );
}

class ElitesApp extends ConsumerWidget {
  const ElitesApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);

    return MaterialApp(
      title: 'Elites',
      debugShowCheckedModeBanner: false,
      // Un seul thème, clair : le rendu sombre donnait des aplats noirs sans
      // relief, très loin de la référence SMAPP dont s'inspire la charte.
      theme: construireTheme(),
      themeMode: ThemeMode.light,
      locale: const Locale('fr'),
      supportedLocales: const [Locale('fr'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: session == null ? const ConnexionPage() : const Coquille(),
    );
  }
}
