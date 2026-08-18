import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/theme.dart';

/// Clôture d'une séance : leçons traitées et observations de fin de cours.
///
/// Vise `POST /ma-journee/{classeMatiereId}`, qui attend l'appel, les leçons
/// et les observations d'un seul tenant — c'est le geste qui marque que le
/// cours a bien eu lieu. L'appel est repris de la base locale plutôt que
/// resaisi : il a déjà été fait sur l'écran d'appel.
class ClotureSeanceSheet extends ConsumerStatefulWidget {
  const ClotureSeanceSheet({super.key, required this.seance});

  final Seance seance;

  static Future<void> ouvrir(BuildContext context, Seance seance) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => ClotureSeanceSheet(seance: seance),
    );
  }

  @override
  ConsumerState<ClotureSeanceSheet> createState() => _ClotureSeanceSheetState();
}

class _ClotureSeanceSheetState extends ConsumerState<ClotureSeanceSheet> {
  late final TextEditingController _contenu;
  late final TextEditingController _observations;
  final Set<int> _leconsTraitees = {};
  bool _envoi = false;

  @override
  void initState() {
    super.initState();
    _contenu = TextEditingController(text: widget.seance.contenu ?? '');
    _observations = TextEditingController(text: widget.seance.observations ?? '');
  }

  @override
  void dispose() {
    _contenu.dispose();
    _observations.dispose();
    super.dispose();
  }

  Future<void> _cloturer() async {
    final classeMatiereId = widget.seance.classeMatiereId;

    if (classeMatiereId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Cette séance n'est rattachée à aucune matière.")),
      );
      return;
    }

    setState(() => _envoi = true);
    final db = ref.read(dbProvider);

    // L'appel déjà pointé localement est renvoyé tel quel : l'API l'exige, et
    // le resaisir ici ferait ressaisir à l'enseignant ce qu'il vient de faire.
    final presences = await (db.select(db.presences)
          ..where((p) => p.seanceId.equals(widget.seance.id)))
        .get();

    final appel = presences
        .map((p) => {
              'eleve_id': p.eleveId,
              'statut': p.statut ?? 'present',
              if ((p.statut ?? 'present') == 'absent') 'motif': p.motif ?? 'inconnu',
            })
        .toList();

    await db.into(db.seances).insertOnConflictUpdate(
          SeancesCompanion(
            id: Value(widget.seance.id),
            schoolId: Value(widget.seance.schoolId),
            classeId: Value(widget.seance.classeId),
            contenu: Value(_contenu.text.trim()),
            observations: Value(_observations.text.trim()),
            statut: const Value('faite'),
            etatSync: const Value('enAttente'),
          ),
        );

    await ref.read(syncServiceProvider.notifier).enfiler(
          methode: 'POST',
          chemin: 'ma-journee/$classeMatiereId',
          corps: {
            'date': widget.seance.dateSeance?.split('T').first,
            'lecons': _leconsTraitees.toList(),
            'appel': appel,
            'observations': _observations.text.trim(),
          },
          entite: 'seances',
          entiteId: widget.seance.id,
        );

    if (!mounted) return;
    Navigator.pop(context);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Séance clôturée — envoi dès que le réseau revient.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final db = ref.watch(dbProvider);

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.8,
      maxChildSize: 0.95,
      builder: (context, controleur) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 10),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Clôturer la séance',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              controller: controleur,
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  controller: _contenu,
                  decoration: const InputDecoration(
                    labelText: 'Contenu traité',
                    alignLabelWithHint: true,
                  ),
                  maxLines: 3,
                ),
                const SizedBox(height: 16),
                Text(
                  'Leçons traitées',
                  style: Theme.of(context).textTheme.labelLarge,
                ),
                const SizedBox(height: 6),
                if (widget.seance.classeMatiereId != null)
                  StreamBuilder<List<ProgressionItem>>(
                    stream: (db.select(db.progressionItems)
                          ..where((p) =>
                              p.classeMatiereId.equals(widget.seance.classeMatiereId!) &
                              p.type.equals('lecon'))
                          ..orderBy([(p) => OrderingTerm(expression: p.ordre)]))
                        .watch(),
                    builder: (context, snapshot) {
                      final lecons = snapshot.data ?? const <ProgressionItem>[];

                      if (lecons.isEmpty) {
                        return Text(
                          'Aucune leçon au programme pour cette matière.',
                          style: TextStyle(
                            fontSize: 13,
                            color: Theme.of(context).colorScheme.outline,
                          ),
                        );
                      }

                      return Column(
                        children: [
                          for (final lecon in lecons)
                            CheckboxListTile(
                              dense: true,
                              contentPadding: EdgeInsets.zero,
                              value: _leconsTraitees.contains(lecon.id),
                              title: Text(lecon.titre, style: const TextStyle(fontSize: 14)),
                              onChanged: (coche) => setState(() {
                                coche == true
                                    ? _leconsTraitees.add(lecon.id)
                                    : _leconsTraitees.remove(lecon.id);
                              }),
                            ),
                        ],
                      );
                    },
                  ),
                const SizedBox(height: 16),
                TextField(
                  controller: _observations,
                  decoration: const InputDecoration(
                    labelText: 'Observations de fin de cours',
                    hintText: 'Difficultés rencontrées, points à revoir, comportement…',
                    alignLabelWithHint: true,
                  ),
                  maxLines: 4,
                  maxLength: 2000,
                ),
              ],
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                onPressed: _envoi ? null : _cloturer,
                icon: const Icon(Icons.task_alt),
                label: const Text('Marquer le cours comme fait'),
                style: FilledButton.styleFrom(backgroundColor: Couleurs.synchro),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
