import 'package:flutter/material.dart';

/// Palette reprise de SMAPP (`smapp_mobile/lib/core/constants/app_colors.dart`)
/// à la demande du client, qui juge son rendu plus abouti.
///
/// L'écart avec le web d'Elites (bleu nuit et or) est donc assumé : le
/// bleu marine reste proche, mais l'accent passe du doré au turquoise.
abstract final class Couleurs {
  static const navy900 = Color(0xFF1A3A6B); // primary — bleu marine
  static const navy800 = Color(0xFF1E5BB5); // primaryLight — bleu roi
  static const navy400 = Color(0xFF6B7280);
  static const gold500 = Color(0xFF2ABFAB); // accent — turquoise
  static const gold100 = Color(0xFFD6F3EF);

  static const fond = Color(0xFFF4F6FA);
  static const surface = Color(0xFFFFFFFF);
  static const texte = Color(0xFF1A3A6B);
  static const texteSecondaire = Color(0xFF6B7280);
  static const separateur = Color(0xFFE5E7EB);

  /// Couleurs d'état de synchronisation. Sémantiques, distinctes de l'accent
  /// de marque : « en attente » ne doit pas se confondre avec l'accent.
  static const enAttente = Color(0xFFF59E0B);
  static const synchro = Color(0xFF2ABFAB);
  static const echec = Color(0xFFEF4444);
}

/// Points de rupture réels, et non une mise à l'échelle proportionnelle : une
/// tablette doit afficher *plus*, pas *plus gros* (cf. conception).
abstract final class Ruptures {
  static const tablette = 600.0;
  static const large = 1024.0;

  static bool estTelephone(BuildContext c) => MediaQuery.sizeOf(c).width < tablette;
  static bool estLarge(BuildContext c) => MediaQuery.sizeOf(c).width >= large;
}

/// Thème unique, clair.
///
/// SMAPP est conçu clair — fond gris pâle, cartes blanches détachées par une
/// ombre. Suivre le mode sombre du téléphone donnait des aplats noirs sans
/// relief, très loin de cette référence : on impose donc le clair plutôt que
/// de livrer deux rendus dont un seul tient la comparaison.
ThemeData construireTheme([Brightness? _]) {
  final schema = ColorScheme.fromSeed(
    seedColor: Couleurs.navy900,
    primary: Couleurs.navy900,
    secondary: Couleurs.navy800,
    tertiary: Couleurs.gold500,
    error: Couleurs.echec,
    surface: Couleurs.surface,
    brightness: Brightness.light,
  );

  return ThemeData(
    useMaterial3: true,
    fontFamily: 'Montserrat',
    colorScheme: schema,
    scaffoldBackgroundColor: Couleurs.fond,
    dividerTheme: const DividerThemeData(color: Couleurs.separateur, space: 1, thickness: 1),

    appBarTheme: const AppBarTheme(
      backgroundColor: Couleurs.surface,
      foregroundColor: Couleurs.texte,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontFamily: 'Montserrat',
        fontSize: 18,
        fontWeight: FontWeight.w700,
        color: Couleurs.texte,
      ),
      iconTheme: IconThemeData(color: Couleurs.texte),
    ),

    // Une ombre légère plutôt qu'une bordure : c'est elle qui détache la
    // carte du fond gris et donne le relief de SMAPP.
    cardTheme: CardThemeData(
      color: Couleurs.surface,
      elevation: 1.5,
      shadowColor: const Color(0x1A000000),
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),

    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Couleurs.surface,
      showDragHandle: true,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
    ),

    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Couleurs.surface,
      indicatorColor: Couleurs.gold100,
      elevation: 3,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (etats) => TextStyle(
          fontFamily: 'Montserrat',
          fontSize: 11.5,
          fontWeight: etats.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
          color: etats.contains(WidgetState.selected)
              ? Couleurs.gold500
              : Couleurs.texteSecondaire,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (etats) => IconThemeData(
          color: etats.contains(WidgetState.selected)
              ? Couleurs.gold500
              : Couleurs.texteSecondaire,
        ),
      ),
    ),

    drawerTheme: const DrawerThemeData(backgroundColor: Couleurs.surface),

    listTileTheme: const ListTileThemeData(
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      titleTextStyle: TextStyle(
        fontFamily: 'Montserrat',
        fontSize: 14.5,
        fontWeight: FontWeight.w600,
        color: Couleurs.texte,
      ),
      subtitleTextStyle: TextStyle(
        fontFamily: 'Montserrat',
        fontSize: 12.5,
        color: Couleurs.texteSecondaire,
      ),
    ),

    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Couleurs.navy900,
        foregroundColor: Colors.white,
        // 48 dp : la cible tactile confortable, pour un enseignant debout qui
        // fait l'appel d'une main.
        minimumSize: const Size(0, 48),
        textStyle: const TextStyle(
          fontFamily: 'Montserrat',
          fontWeight: FontWeight.w700,
          fontSize: 14.5,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),

    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: Couleurs.gold500,
      foregroundColor: Colors.white,
    ),

    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Couleurs.fond,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Couleurs.separateur),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Couleurs.separateur),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Couleurs.navy800, width: 1.6),
      ),
    ),

    textTheme: const TextTheme(
      titleLarge: TextStyle(fontWeight: FontWeight.w700, color: Couleurs.texte),
      titleMedium: TextStyle(fontWeight: FontWeight.w700, color: Couleurs.texte),
      bodyMedium: TextStyle(color: Couleurs.texte),
      bodySmall: TextStyle(color: Couleurs.texteSecondaire),
    ),
  );
}
