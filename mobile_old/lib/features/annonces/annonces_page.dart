import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

/// Annonces de l'établissement et notifications personnelles, réunies en deux
/// onglets d'un même écran.
///
/// Les deux répondent à la question « qu'est-ce que je dois savoir ? », mais
/// l'une s'adresse à tous et l'autre à moi seul — d'où la séparation en
/// onglets plutôt qu'une liste mélangée où le personnel se perdrait.
class AnnoncesPage extends ConsumerWidget {
  const AnnoncesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          const TabBar(tabs: [
            Tab(text: 'Notifications'),
            Tab(text: 'Annonces'),
          ]),
          const Expanded(
            child: TabBarView(children: [_Notifications(), _Annonces()]),
          ),
        ],
      ),
    );
  }
}

class _Notifications extends ConsumerWidget {
  const _Notifications();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder<List<NotificationsInterne>>(
      stream: (db.select(db.notificationsInternes)
            ..orderBy([(n) => OrderingTerm(expression: n.id, mode: OrderingMode.desc)]))
          .watch(),
      builder: (context, snapshot) {
        final lignes = snapshot.data ?? const <NotificationsInterne>[];

        if (lignes.isEmpty) {
          return const EtatVide(
            message: 'Aucune notification.',
            icone: Icons.notifications_none,
          );
        }

        return ListView.separated(
          itemCount: lignes.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (_, i) {
            final n = lignes[i];
            return ListTile(
              leading: Icon(
                _icone(n.type),
                color: n.lu ? Theme.of(context).colorScheme.outline : Couleurs.gold500,
              ),
              title: Text(
                n.titre,
                style: TextStyle(fontWeight: n.lu ? FontWeight.normal : FontWeight.bold),
              ),
              subtitle: Text(
                n.message ?? '',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12.5),
              ),
              onTap: n.lu ? null : () => _marquerLue(ref, n),
            );
          },
        );
      },
    );
  }

  IconData _icone(String? type) => switch (type) {
        'absence' => Icons.event_busy_outlined,
        'paiement' => Icons.payments_outlined,
        'annonce' => Icons.campaign_outlined,
        _ => Icons.notifications_outlined,
      };

  /// Marque lue en local d'abord : la pastille disparaît au doigt, sans
  /// attendre le réseau. L'appel serveur suit par l'outbox.
  Future<void> _marquerLue(WidgetRef ref, NotificationsInterne n) async {
    final db = ref.read(dbProvider);

    await (db.update(db.notificationsInternes)..where((x) => x.id.equals(n.id)))
        .write(const NotificationsInternesCompanion(
      lu: Value(true),
      etatSync: Value('enAttente'),
    ));

    await ref.read(syncServiceProvider.notifier).enfiler(
          methode: 'POST',
          chemin: 'notifications/${n.id}/lire',
          corps: const {},
          entite: 'notifications_internes',
          entiteId: n.id,
        );
  }
}

class _Annonces extends ConsumerWidget {
  const _Annonces();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder<List<Annonce>>(
      stream: (db.select(db.annonces)
            ..orderBy([(a) => OrderingTerm(expression: a.publieeLe, mode: OrderingMode.desc)]))
          .watch(),
      builder: (context, snapshot) {
        final lignes = snapshot.data ?? const <Annonce>[];

        if (lignes.isEmpty) {
          return const EtatVide(
            message: 'Aucune annonce publiée.',
            icone: Icons.campaign_outlined,
          );
        }

        return ListView.builder(
          padding: const EdgeInsets.all(12),
          itemCount: lignes.length,
          itemBuilder: (_, i) {
            final a = lignes[i];
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.campaign_outlined, size: 18, color: Couleurs.gold500),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            a.titre,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                        ),
                      ],
                    ),
                    if (a.publieeLe != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        a.publieeLe!.split('T').first,
                        style: TextStyle(
                          fontSize: 11.5,
                          color: Theme.of(context).colorScheme.outline,
                        ),
                      ),
                    ],
                    if (a.contenu != null && a.contenu!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(a.contenu!, style: const TextStyle(fontSize: 13.5)),
                    ],
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}
