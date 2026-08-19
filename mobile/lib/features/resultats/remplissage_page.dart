import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/nav/barre_app.dart';
import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

final _remplissageProvider =
    FutureProvider.family<Map<String, dynamic>, int>((ref, classeId) async {
  final reponse = await ref.watch(apiClientProvider).get('classes/$classeId/remplissage');
  final data = reponse['data'];
  return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
});

/// État de remplissage des notes, matière par matière.
///
/// L'écran du censeur : il répond à « qui n'a pas encore saisi ses notes ? »
/// à l'approche des conseils. Les matières les moins avancées remontent en
/// tête — c'est là que se trouvent les relances à faire, pas dans celles qui
/// sont déjà complètes.
class RemplissagePage extends ConsumerStatefulWidget {
  const RemplissagePage({super.key});

  @override
  ConsumerState<RemplissagePage> createState() => _RemplissagePageState();
}

class _RemplissagePageState extends ConsumerState<RemplissagePage> {
  Map<String, dynamic>? _classe;

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(listeApiProvider(const RequeteListe('classes')));

    return Scaffold(
      appBar: BarreApp(titre: 'Remplissage des notes'),
      body: classes.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(listeApiProvider(const RequeteListe('classes'))),
        ),
        data: (liste) {
          if (liste.isEmpty) return const EtatVide(message: 'Aucune classe.');

          final classe = _classe ?? liste.first;

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: DropdownButtonFormField<int>(
                  initialValue: classe['id'] as int?,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Classe', isDense: true),
                  items: [
                    for (final c in liste)
                      DropdownMenuItem(value: c['id'] as int?, child: Text('${c['nom']}')),
                  ],
                  onChanged: (id) =>
                      setState(() => _classe = liste.firstWhere((c) => c['id'] == id)),
                ),
              ),
              const Divider(height: 1),
              Expanded(child: _Etat(classeId: classe['id'] as int)),
            ],
          );
        },
      ),
    );
  }
}

class _Etat extends ConsumerWidget {
  const _Etat({required this.classeId});

  final int classeId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_remplissageProvider(classeId));

    return async.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => EtatErreur(
        message: e is ErreurApi ? e.message : '$e',
        onReessayer: () => ref.invalidate(_remplissageProvider(classeId)),
      ),
      data: (donnees) {
        final matieres = (donnees['matieres'] as List?)?.cast<Map<String, dynamic>>() ?? const [];

        if (matieres.isEmpty) {
          return const EtatVide(
            message: 'Aucune matière affectée à cette classe.',
            icone: Icons.fact_check_outlined,
          );
        }

        // Les moins avancées d'abord : ce sont elles qui appellent une action.
        final triees = [...matieres]
          ..sort((a, b) => ((a['taux'] as num?) ?? 0).compareTo((b['taux'] as num?) ?? 0));

        final trimestre = donnees['trimestre'];
        final moyenne = matieres.isEmpty
            ? 0.0
            : matieres.fold<num>(0, (t, m) => t + ((m['taux'] as num?) ?? 0)) / matieres.length;

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(_remplissageProvider(classeId)),
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                color: Couleurs.navy900.withValues(alpha: 0.04),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      trimestre is Map ? '${trimestre['libelle']}' : '',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(
                              value: moyenne / 100,
                              minHeight: 8,
                              backgroundColor: Couleurs.separateur,
                              valueColor: AlwaysStoppedAnimation(_couleur(moyenne)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text('${moyenne.toStringAsFixed(0)} %',
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                      ],
                    ),
                  ],
                ),
              ),
              Expanded(
                child: ListView.separated(
                  itemCount: triees.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final m = triees[i];
                    final taux = ((m['taux'] as num?) ?? 0).toDouble();

                    return ListTile(
                      title: Text('${m['matiere'] ?? '—'}'),
                      subtitle: Text(
                        '${m['enseignant'] ?? 'Aucun enseignant'}',
                        style: const TextStyle(fontSize: 12.5),
                      ),
                      trailing: SizedBox(
                        width: 92,
                        child: Row(
                          children: [
                            Expanded(
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(5),
                                child: LinearProgressIndicator(
                                  value: taux / 100,
                                  minHeight: 6,
                                  backgroundColor: Couleurs.separateur,
                                  valueColor: AlwaysStoppedAnimation(_couleur(taux)),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            SizedBox(
                              width: 34,
                              child: Text(
                                '${taux.toStringAsFixed(0)}%',
                                textAlign: TextAlign.right,
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: _couleur(taux),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  /// Trois paliers plutôt qu'un dégradé : le censeur cherche à distinguer
  /// « fait », « en cours » et « pas commencé », pas à lire une nuance.
  static Color _couleur(num taux) => switch (taux) {
        >= 100 => Couleurs.synchro,
        >= 50 => Couleurs.enAttente,
        _ => Couleurs.echec,
      };
}
