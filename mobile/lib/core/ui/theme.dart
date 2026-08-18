import 'package:flutter/material.dart';

/// Identité visuelle reprise du web (`web/src/index.css`) : le bleu nuit et
/// l'or de la Fondation. Un parent qui passe du portail à l'app doit
/// reconnaître le même établissement.
abstract final class Couleurs {
  static const navy900 = Color(0xFF08152B);
  static const navy800 = Color(0xFF0B1D3C);
  static const navy400 = Color(0xFF64748B);
  static const gold500 = Color(0xFFC9891F);
  static const gold100 = Color(0xFFF5E6C8);
  static const cream50 = Color(0xFFFAF6EC);

  /// Fond d'écran général : un gris très légèrement bleuté, repris du parti
  /// pris de _smapp. C'est lui qui fait ressortir les cartes blanches — sur un
  /// fond blanc, une carte blanche disparaît et l'écran paraît plat.
  static const fond = Color(0xFFF4F6FA);
  static const separateur = Color(0xFFE5E7EB);
  static const texteSecondaire = Color(0xFF6B7280);

  /// Couleurs d'état de synchronisation. Sémantiques, distinctes de l'accent
  /// de marque : « en attente » ne doit pas se confondre avec « doré ».
  static const enAttente = Color(0xFF9A6410);
  static const synchro = Color(0xFF1D7A4C);
  static const echec = Color(0xFFA33A2D);
}

/// Points de rupture réels, et non une mise à l'échelle proportionnelle : une
/// tablette doit afficher *plus*, pas *plus gros* (cf. conception).
abstract final class Ruptures {
  static const tablette = 600.0;
  static const large = 1024.0;

  static bool estTelephone(BuildContext c) => MediaQuery.sizeOf(c).width < tablette;
  static bool estLarge(BuildContext c) => MediaQuery.sizeOf(c).width >= large;
}

ThemeData construireTheme(Brightness luminosite) {
  final sombre = luminosite == Brightness.dark;

  final schema = ColorScheme.fromSeed(
    seedColor: Couleurs.navy800,
    brightness: luminosite,
  ).copyWith(secondary: Couleurs.gold500);

  return ThemeData(
    useMaterial3: true,
    colorScheme: schema,
    scaffoldBackgroundColor: sombre ? const Color(0xFF0E1116) : Couleurs.fond,
    appBarTheme: AppBarTheme(
      backgroundColor: sombre ? const Color(0xFF141A21) : Colors.white,
      foregroundColor: sombre ? Colors.white : Couleurs.navy900,
      elevation: 0,
      scrolledUnderElevation: 1,
      centerTitle: false,
      titleTextStyle: TextStyle(
        color: sombre ? Colors.white : Couleurs.navy900,
        fontSize: 20,
        fontWeight: FontWeight.w700,
      ),
    ),
    // Ombre portée légère plutôt qu'un liseré : c'est elle qui détache la
    // carte du fond et donne du relief à l'écran. Un contour seul restait
    // plat, ce qui rendait les listes ternes.
    cardTheme: CardThemeData(
      elevation: sombre ? 0 : 1.5,
      shadowColor: const Color(0x1A000000),
      color: sombre ? null : Colors.white,
      surfaceTintColor: Colors.transparent,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: sombre ? BorderSide(color: schema.outlineVariant) : BorderSide.none,
      ),
    ),
    dividerTheme: DividerThemeData(
      color: sombre ? null : Couleurs.separateur,
      space: 1,
      thickness: 1,
    ),
    // La barre de navigation reprend le fond des surfaces et marque la
    // destination active à l'or de la marque.
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: sombre ? const Color(0xFF141A21) : Colors.white,
      indicatorColor: Couleurs.gold100,
      elevation: 3,
      surfaceTintColor: Colors.transparent,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (etats) => TextStyle(
          fontSize: 11.5,
          fontWeight: etats.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
          color: etats.contains(WidgetState.selected)
              ? (sombre ? Couleurs.gold500 : Couleurs.navy900)
              : Couleurs.texteSecondaire,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (etats) => IconThemeData(
          size: 23,
          color: etats.contains(WidgetState.selected)
              ? (sombre ? Couleurs.gold500 : Couleurs.navy900)
              : Couleurs.texteSecondaire,
        ),
      ),
    ),
    // Les feuilles portent l'essentiel de la navigation secondaire : elles
    // méritent d'être définies une fois pour toutes plutôt que par appel.
    bottomSheetTheme: const BottomSheetThemeData(
      showDragHandle: true,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
    ),
    listTileTheme: const ListTileThemeData(
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        // 48 dp : la cible tactile confortable, pour un enseignant debout qui
        // fait l'appel d'une main.
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: schema.outlineVariant),
      ),
    ),
  );
}
