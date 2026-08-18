import 'package:drift/drift.dart' show OrderingTerm;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

/// Centre de synchronisation : ce qui attend, ce qui a échoué et pourquoi.
///
/// C'est le pendant honnête des ✓✓ de WhatsApp. Sans cet écran, une opération
/// refusée par le serveur resterait invisible et l'utilisateur croirait sa
/// saisie enregistrée.
class CentreSyncSheet extends ConsumerWidget {
  const CentreSyncSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);
    final etat = ref.watch(syncServiceProvider);

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.6,
      maxChildSize: 0.92,
      builder: (context, controleur) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 12, 8),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Synchronisation',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      Text(
                        etat.horsLigne
                            ? 'Hors connexion'
                            : etat.derniereReussite == null
                                ? 'Jamais synchronisé'
                                : 'À jour',
                        style: TextStyle(
                          fontSize: 12.5,
                          color: etat.horsLigne ? Couleurs.enAttente : Couleurs.navy400,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: etat.enCours
                      ? null
                      : () => ref.read(syncServiceProvider.notifier).synchroniser(),
                  icon: const Icon(Icons.sync, size: 18),
                  label: Text(etat.enCours ? 'En cours…' : 'Synchroniser'),
                ),
              ],
            ),
          ),
          if (etat.panne != null)
            Container(
              width: double.infinity,
              color: Couleurs.echec.withValues(alpha: 0.12),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.sync_problem, size: 16, color: Couleurs.echec),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'La synchronisation a échoué',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.bold,
                            color: Couleurs.echec,
                          ),
                        ),
                        Text(
                          etat.panne!,
                          style: const TextStyle(fontSize: 11.5, color: Couleurs.echec),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          if (etat.derniereReussite != null)
            BandeauPerime(depuis: etat.derniereReussite),
          const Divider(height: 1),
          Expanded(
            child: StreamBuilder(
              stream: (db.select(db.outboxOperations)
                    ..orderBy([(o) => OrderingTerm(expression: o.creeLe)]))
                  .watch(),
              builder: (context, snapshot) {
                final operations = snapshot.data ?? const [];

                if (operations.isEmpty) {
                  return const EtatVide(
                    message: 'Tout est synchronisé.',
                    icone: Icons.cloud_done_outlined,
                  );
                }

                return ListView.separated(
                  controller: controleur,
                  itemCount: operations.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final o = operations[i];
                    // Une opération sans prochain essai programmé mais déjà
                    // tentée a été refusée définitivement (4xx).
                    final bloquee = o.prochainEssai == null && o.tentatives > 0;

                    return ListTile(
                      leading: Icon(
                        bloquee ? Icons.error_outline : Icons.schedule,
                        color: bloquee ? Couleurs.echec : Couleurs.enAttente,
                      ),
                      title: Text('${o.methode} ${o.chemin}'),
                      subtitle: Text(
                        bloquee
                            ? (o.derniereErreur ?? 'Refusée par le serveur')
                            : 'En attente d\'envoi'
                                '${o.tentatives > 0 ? ' — ${o.tentatives} tentative(s)' : ''}',
                        style: TextStyle(
                          color: bloquee ? Couleurs.echec : null,
                          fontSize: 12.5,
                        ),
                      ),
                      trailing: bloquee
                          ? IconButton(
                              tooltip: 'Abandonner',
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => (db.delete(db.outboxOperations)
                                    ..where((x) => x.id.equals(o.id)))
                                  .go(),
                            )
                          : null,
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
