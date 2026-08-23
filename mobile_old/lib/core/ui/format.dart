import 'package:intl/intl.dart';

/// Montants en francs CFA : entier, séparateur de milliers par espace
/// insécable, symbole après le nombre — la convention locale.
///
/// Le franc CFA n'a pas de subdivision en usage : afficher des décimales
/// donnerait une fausse précision sur des sommes qui se règlent au franc près.
final _formatMontant = NumberFormat.decimalPattern('fr');

String formaterMontant(dynamic valeur) {
  final nombre = valeur is num ? valeur : num.tryParse('$valeur');
  if (nombre == null) return '—';

  return '${_formatMontant.format(nombre.round())} F';
}

/// Date ISO renvoyée par l'API vers le format jour/mois/année attendu ici.
String formaterDate(dynamic valeur) {
  if (valeur == null) return '—';
  final date = DateTime.tryParse('$valeur');
  if (date == null) return '$valeur';

  return DateFormat('dd/MM/yyyy').format(date);
}

/// Variante courte pour les listes denses, où l'année encombre.
String formaterDateCourte(dynamic valeur) {
  if (valeur == null) return '';
  final date = DateTime.tryParse('$valeur');
  if (date == null) return '$valeur';

  return DateFormat('dd/MM').format(date);
}
