import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:workmanager/workmanager.dart';

import '../session/session.dart';
import 'sync_service.dart';

/// Nom de la tâche périodique, réutilisé pour l'annuler à la déconnexion.
const _tacheSync = 'sync-outbox';

/// Point d'entrée de la tâche de fond.
///
/// Android la réveille dans un **isolate séparé** : rien n'est partagé avec
/// l'application: ni providers, ni base ouverte, ni session en mémoire. Tout
/// doit donc être reconstruit ici, d'où le conteneur Riverpod monté à la volée
/// puis refermé.
@pragma('vm:entry-point')
void pointEntreeTacheFond() {
  Workmanager().executeTask((tache, _) async {
    if (tache != _tacheSync) return true;

    final conteneur = ProviderContainer();

    try {
      // Sans session restaurée, aucune requête ne serait authentifiée : la
      // tâche s'arrête proprement plutôt que d'échouer en 401 en boucle.
      await conteneur.read(sessionProvider.notifier).restaurer();
      if (conteneur.read(sessionProvider) == null) return true;

      await conteneur.read(syncServiceProvider.notifier).synchroniser();
      return true;
    } catch (_) {
      // Renvoyer `false` demanderait à Android de réessayer avec son propre
      // back-off, qui s'ajouterait à celui de l'outbox. Un seul mécanisme de
      // report suffit — c'est celui du moteur qui fait autorité.
      return true;
    } finally {
      conteneur.dispose();
    }
  });
}

/// Programme le drain périodique.
///
/// Quinze minutes est le plancher imposé par Android ; la contrainte réseau
/// évite de réveiller l'app pour rien en zone blanche.
Future<void> programmerSyncFond() async {
  await Workmanager().initialize(pointEntreeTacheFond);

  await Workmanager().registerPeriodicTask(
    _tacheSync,
    _tacheSync,
    frequency: const Duration(minutes: 15),
    constraints: Constraints(networkType: NetworkType.connected),
    existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
  );
}

/// À appeler à la déconnexion : un appareil rendu ne doit plus rien
/// synchroniser au nom de son ancien utilisateur.
Future<void> annulerSyncFond() => Workmanager().cancelByUniqueName(_tacheSync);
