import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../session/session.dart';

/// Le privilège d'écriture est-il détenu ?
///
/// Interrogé avant d'afficher un bouton, pas après la saisie : laisser
/// quelqu'un remplir un formulaire pour le refuser en 403 à l'envoi est le
/// pire des enchaînements. Le serveur reste juge — cette vérification ne fait
/// que masquer ce qu'il refuserait de toute façon.
bool peutEcrire(BuildContext context, String permission) {
  final conteneur = ProviderScope.containerOf(context, listen: false);
  final session = conteneur.read(sessionProvider);
  if (session == null) return false;

  // Le super administrateur passe outre, comme `Gate::before` au serveur.
  return session.estSuperAdmin || session.peut(permission);
}
